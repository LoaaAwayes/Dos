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


    // protected function getNextCatalogUrl(): string
    // {
    //     if (empty($this->catalogReplicas) || count($this->catalogReplicas) === 0 || empty($this->catalogReplicas[0])) {
    //         throw new \Exception("Catalog replica URLs are not configured.");
    //     }

       
    //     $catalogURL = $this->catalogReplicas[self::$currentCatalogIndex];
    //     self:: $currentCatalogIndex = (self::$currentCatalogIndex + 1) % count($this->catalogReplicas);
       
    //     return $catalogURL;
    // }

    // protected function getNextOrderUrl(): string
    // {
    //     if (empty($this->orderReplicas) || empty($this->orderReplicas[0])) {
    //         throw new \Exception("Order replica URLs are not configured.");
    //     }
    //     $url = $this->orderReplicas[self::$currentOrderIndex];
    //     self::$currentOrderIndex = (self::$currentOrderIndex + 1) % count($this->orderReplicas);
    //     return $url;
    // }

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
    
    Cache::add($key, $data, Carbon::now()->addMinutes(1)); 
   }


    protected function invalidateCache(string $key): void
    {
   
    Cache::forget($key); 
    }

// service methods 
//    Search for books by title



    // protected function search($requestPath)
    // {
    //     $cachedData = $this->getFromCache($requestPath);
    //     if ($cachedData !== null) {
    //         return response()->json(["Cache-Status:" => "HIT",'data:' => $cachedData,] ,200);
    //     }
    //     try {
    //         $catalogUrl = $this->getNextCatalogUrl();
    //         $response = $this->client->get("{$catalogUrl}/{$requestPath}");

    //         $data = json_decode($response->getBody());

    //         $this->putInCache($requestPath, $data);

    //         return response()->json(["Cache-Status:" => "MISS",'data:' => $data,] ,200);
    //     } catch (Exception $e) {
    //         return response()->json(["error" => "Failed to search catalog service due to an internal error."], 500);
    //     }
    // }

//    Get book details by ID

    // protected function getBookInfo($requestPath)
    // {
    //     $cachedData = $this->getFromCache($requestPath);
    //     if ($cachedData !== null) {
    //         return response()->json( ["Cache-Status" => "HIT", 'data'=> $cachedData], 200);
    //     }
    
    //     try{
    //         $catalogUrl = $this->getNextCatalogUrl();
    //         $response = $this->client->get("{$catalogUrl}/{$requestPath}");
    //         $data = json_decode($response->getBody());
    //         $this->putInCache($requestPath, $data);
    //          return response()->json( ["Cache-Status" => "MISS", 'data'=> $data], 200);
    //         } catch (Exception $e) {
    //         return response()->json(["error" => "Failed to get Book Info from catalog service due to an internal error."], 500);
    //         }
    // }


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
    
    if (preg_match('/\/(\d+)$/', $requestPath, $matches)) {
        $itemId = $matches[1];
    }
    
    if ($itemId) {
        // Invalidate direct item cache
        $this->invalidateCache("item/{$itemId}");
        
        // Invalidate all tracked keys that might contain this item
        $itemKeys = Cache::get('item_keys', []);
        $searchKeys = Cache::get('search_keys', []);
        
        $allKeys = array_merge($itemKeys, $searchKeys);
        
        foreach ($allKeys as $key) {
            if (str_contains($key, (string)$itemId)) {
                $this->invalidateCache($key);
            }
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
            // Invalidate cache before making the write operation
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
            
            return response()->json(json_decode($response->getBody()), 200);
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

    // protected function purchase($requestPath)
    // {
    //     try {
    //         $orderUrl = $this->getNextOrderUrl();
    //         $response = $this->client->post("{$orderUrl}/{$requestPath}");
    //         return response()->json(json_decode($response->getBody()), 200);
    //     } catch (Exception $e) {
    //         return response()->json(["error" => "Failed to process purchase with order service due to an internal error."
    //     ,'error' => $e->getMessage()], 500);
    //     }
    // }

    // protected function order($requestPath)
    // {
    //     try {
    //         $catalogUrl = $this->getNextCatalogUrl();
    //         $response = $this->client->post("{$catalogUrl}/{$requestPath}");
    //         return response()->json(json_decode($response->getBody()), 200);
    //     } catch (Exception $e) {
    //         return response()->json(["error" => "Failed to process order/update with catalog service due to an internal error."], 500);
    //     }
    // }

    // protected function updateItem($requestPath)
    // {
    //     try {
    //         $catalogUrl = $this->getNextCatalogUrl();
    //         $payload = request()->json()->all();
    //         $response = $this->client->put("{$catalogUrl}/{$requestPath}", [
    //             "json" => $payload 
    //         ]);
    //         if ($response){
            
    //             $this->invalidateCache($requestPath);
    //             return response()->json(json_decode($response->getBody()), 200);

    //         }
    //         return response()->json(json_decode($response->getBody()), 200);
    //     } catch (Exception $e) {
    //         return response()->json(["error" => "Failed to update item in catalog service due to an internal error."
    //         ,'error' => $e->getMessage()], 500);
    //     }
    // }



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
