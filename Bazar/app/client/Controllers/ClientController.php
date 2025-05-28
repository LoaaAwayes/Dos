<?php
namespace App\client\Controllers;

use Laravel\Lumen\Routing\Controller;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ClientController extends Controller
{
    protected $client;

    public function __construct()
{
           try {
            $this->client = new Client([
            'timeout' => 30,          
            'connect_timeout' => 5,   
            'verify' => false,  
            ]);
        } catch (Exception $e) {
             return response()->json(["error" => "Failed to initialize HTTP client.",'error'=> $e->getMessage()], 500);
        }
}


    // Replica URLs - Pointing to local mock services for testing
    protected $catalogReplicas = [
        "http://localhost:9001/catalog",
        "http://localhost:9002/catalog2"
    ];
    protected $orderReplicas = [
         "http://localhost:9003/order",
         "http://localhost:9004/order2"
    ];
    protected $catalogUrl;

    protected $orderUrl;
    // Indices for round-robin (static for persistence within a single process)
    protected static $currentCatalogIndex = 0;
    protected static $currentOrderIndex = 0;



    protected function getNextCatalogUrl(): string
{
    if (empty($this->catalogReplicas)) {
        throw new \Exception("Catalog replica URLs are not configured.");
    }

    $currentIndex = Cache::get('current_catalog_index', 0);
    $url = $this->catalogReplicas[$currentIndex];
    
    $newIndex = ($currentIndex + 1) % count($this->catalogReplicas);
     Cache::forget('current_catalog_index');
    Cache::put('current_catalog_index', $newIndex, Carbon::now()->addMinutes(10));
    
    return $url;
}

protected function getNextOrderUrl(): string
{
    if (empty($this->orderReplicas)) {
        throw new \Exception("Order replica URLs are not configured.");
    }

    $currentIndex = Cache::get('current_order_index', 0);
    $url = $this->orderReplicas[$currentIndex];
    
    $newIndex = ($currentIndex + 1) % count($this->orderReplicas);
    Cache::forget('current_order_index');
    Cache::put('current_order_index', $newIndex, Carbon::now()->addMinutes(10));
    
    return $url;
}

    // --- Caching Methods ---
    protected function getFromCache(string $key)
    {
        // $data = self::$cache[$key] ?? null;
        $data = Cache::get($key);

        return $data;

    }

   protected function putInCache(string $key, $data): void
   {
    
    Cache::put($key, $data, Carbon::now()->addMinutes(1)); 
   }


    protected function invalidateCache(string $key): void
    {
   
    Cache::forget($key); 
    }

// service methods 
//    Search for books by title





    protected function getBookInfo($requestPath)
{
    $cachedData = $this->getFromCache($requestPath);
    if ($cachedData !== null) {
        return response()->json(["Cache-Status" => "HIT", 'data' => $cachedData], 200);
    }

    try {
        $catalogUrl = $this->getNextCatalogUrl();
        $response = $this->client->get("{$catalogUrl}/{$requestPath}");
        $data = json_decode($response->getBody());
        
        // Store the data in cache
        $this->putInCache($requestPath, $data);
        
        // Track this item key for potential future invalidation
        $itemKeys = Cache::get('item_keys', []);
        if (!in_array($requestPath, $itemKeys)) {
            $itemKeys[] = $requestPath;
            Cache::put('item_keys', $itemKeys, Carbon::now()->addHours(1));
        }
        
        return response()->json(["Cache-Status" => "MISS", 'data' => $data], 200);
    } catch (Exception $e) {
        return response()->json([
            "error" => "Failed to get Book Info from catalog service due to an internal error.",
            "details" => $e->getMessage()
        ], 500);
    }
}


protected function invalidateItemCache(string $requestPath): void
{
    // Extract item ID from different path patterns
    $itemId = null;
    
    // Match patterns like /book/3, /item/3, or just /3
    if (preg_match('/(?:book|item)\/(\d+)$/', $requestPath, $matches) || 
        preg_match('/\/(\d+)(?:\?|$)/', $requestPath, $matches)) {
        $itemId = $matches[1];
    }
    
    if ($itemId) {
        // Invalidate all direct item cache patterns
        $this->invalidateCache("item/{$itemId}");
        $this->invalidateCache("book/{$itemId}");
        $this->invalidateCache($requestPath); // Invalidate the exact request path
        
        // Invalidate all search-related caches that might contain this item
        $searchKeys = Cache::get('search_keys', []);
        foreach ($searchKeys as $key) {
            // Invalidate all search results that might include this book
            if (str_contains($key, 'search/')) {
                $this->invalidateCache($key);
            }
        }
        
        // Also invalidate any topic-based searches that might include this book
        $topicKeys = array_filter($searchKeys, function($key) {
            return str_contains($key, 'topic/');
        });
        foreach ($topicKeys as $key) {
            $this->invalidateCache($key);
        }
    }
    
    // Additionally, invalidate all cached responses that might contain this book's data
    $itemKeys = Cache::get('item_keys', []);
    foreach ($itemKeys as $key) {
        if (str_contains($key, (string)$itemId)) {
            $this->invalidateCache($key);
        }
    }
}

    protected function purchase($requestPath)
    {
        try {
            // Invalidate cache before making the write operation
            $this->invalidateItemCache($requestPath);
            
            $orderUrl = $this->getNextOrderUrl();
            $response = $this->client->post("{$orderUrl}/{$requestPath}", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            return response()->json(json_decode($response->getBody()), 200);
        } catch (Exception $e) {
            return response()->json([
                "error" => "Failed to process purchase with order service due to an internal error.",
                "details" => $e->getMessage()
            ], 500);
        }
    }

    protected function order($requestPath)
    {
        try {
            // Invalidate cache before making the write operation
            $this->invalidateItemCache($requestPath);
            
            $catalogUrl = $this->getNextCatalogUrl();
            $response = $this->client->post("{$catalogUrl}/{$requestPath}", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            return response()->json(json_decode($response->getBody()), 200);
        } catch (Exception $e) {
            return response()->json([
                "error" => "Failed to process order with catalog service due to an internal error.",
                "details" => $e->getMessage()
            ], 500);
        }
    }

  


    protected function updateItem($requestPath)
{
    try {
        // First invalidate all relevant cache entries
        $this->invalidateItemCache($requestPath);
        
        $catalogUrl = $this->getNextCatalogUrl();
        $payload = request()->json()->all();
        $response = $this->client->put("{$catalogUrl}/{$requestPath}", [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            "json" => $payload 
        ]);
        
        // Get the updated book data
        $updatedData = json_decode($response->getBody(), true);
        
        // If we have an ID from the response, invalidate again with more context
        if (isset($updatedData['id'])) {
            $this->invalidateItemCache("item/{$updatedData['id']}");
            $this->invalidateItemCache("book/{$updatedData['id']}");
        }
        
        // Also invalidate the exact request path again
        $this->invalidateItemCache($requestPath);
        
        return response()->json($updatedData, 200);
    } catch (Exception $e) {
        return response()->json([
            "error" => "Failed to update item in catalog service due to an internal error.",
            "details" => $e->getMessage()
        ], 500);
    }
}
  //  Modified search method to track search keys

    protected function search($requestPath)
    {
        $cachedData = $this->getFromCache($requestPath);
        if ($cachedData !== null) {
            return response()->json(["Cache-Status" => "HIT", 'data' => $cachedData], 200);
        }
        
        try {
            $catalogUrl = $this->getNextCatalogUrl();
            $response = $this->client->get("{$catalogUrl}/{$requestPath}");
            $data = json_decode($response->getBody());

            $this->putInCache($requestPath, $data);
            
            // Track this search key for potential future invalidation
            $searchKeys = Cache::get('search_keys', []);
            if (!in_array($requestPath, $searchKeys)) {
                $searchKeys[] = $requestPath;
                Cache::put('search_keys', $searchKeys, Carbon::now()->addHours(1));
            }
            
            return response()->json(["Cache-Status" => "MISS", 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json([
                "error" => "Failed to search catalog service due to an internal error.",
                "details" => $e->getMessage()
            ], 500);
        }
    }



    public function handleFront(Request $request)
    {
          $fullRequest = $request->getRequestUri(); 

        $cleanedRequest = str_replace('/client/', '', $fullRequest);

        Log::info("Entered handleFront with URI: " . $request->getRequestUri());



            if (str_starts_with($cleanedRequest, "search/")) {
                return $this->search($cleanedRequest);

            } elseif (str_starts_with($cleanedRequest, "item/")) {
                return $this->getBookInfo($cleanedRequest);
            }
            if (str_starts_with($cleanedRequest, "purchase/")) {
                return $this->purchase($cleanedRequest);
            } elseif (str_starts_with($cleanedRequest, "order/")) {
                return $this->order($cleanedRequest);
            }
             if (str_starts_with($cleanedRequest, "book/")) {
                return $this->updateItem($cleanedRequest);
            }
        

        return response()->json(["error" => "Service not found for the requested path and method."], 404);

    }


}
