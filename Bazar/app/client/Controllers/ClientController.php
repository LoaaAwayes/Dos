<?php
namespace App\client\Controllers;

use Laravel\Lumen\Routing\Controller;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
class ClientController extends Controller
{
    protected $client;

    // Replica URLs - Pointing to local mock services for testing
    protected $catalogReplicas = [
        "http://localhost:8001/catalog",
        "http://localhost:8002/catalog2"
    ];
    protected $orderReplicas = [
         "http://localhost:8003/order",
         "http://localhost:8004/order2"
    ];

    // Indices for round-robin (static for persistence within a single process)
    protected static $currentCatalogIndex = 0;
    protected static $currentOrderIndex = 0;


    protected function getNextCatalogUrl(): string
    {
        if (empty($this->catalogReplicas) || count($this->catalogReplicas) === 0 || empty($this->catalogReplicas[0])) {
            throw new \Exception("Catalog replica URLs are not configured.");
        }

       
        $catalogURL = $this->catalogReplicas[self::$currentCatalogIndex];
        self:: $currentCatalogIndex = (self::$currentCatalogIndex + 1) % count($this->catalogReplicas);
       
        return $catalogURL;
    }

    protected function getNextOrderUrl(): string
    {
        if (empty($this->orderReplicas) || empty($this->orderReplicas[0])) {
            throw new \Exception("Order replica URLs are not configured.");
        }
        $url = $this->orderReplicas[self::$currentOrderIndex];
        self::$currentOrderIndex = (self::$currentOrderIndex + 1) % count($this->orderReplicas);
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
    
    Cache::put($key, $data, Carbon::now()->addMinutes(30)); 
   }


    protected function invalidateCache(string $key): void
    {
   
    Cache::forget($key); 
    }

//service methods 
   // Search for books by title

    protected function search($requestPath)
    {
        $cachedData = $this->getFromCache($requestPath);
        if ($cachedData !== null) {
            return response()->json(["Cache-Status:" => "HIT",'data:' => $cachedData,] ,200);
        }
        try {
            $catalogUrl = $this->getNextCatalogUrl();
            $response = $this->client->get("{$catalogUrl}/{$requestPath}");

            $data = json_decode($response->getBody());

            $this->putInCache($requestPath, $data);

            return response()->json(["Cache-Status:" => "MISS",'data:' => $data,] ,200);
        } catch (Exception $e) {
            return response()->json(["error" => "Failed to search catalog service due to an internal error."], 500);
        }
    }

   // Get book details by ID

    protected function getBookInfo($requestPath)
    {
        $cachedData = $this->getFromCache($requestPath);
        if ($cachedData !== null) {
            return response()->json( ["Cache-Status" => "HIT", 'data'=> $cachedData], 200);
        }
    
        try{
            $catalogUrl = $this->getNextCatalogUrl();
            $response = $this->client->get("{$catalogUrl}/{$requestPath}");
            $data = json_decode($response->getBody());
            $this->putInCache($requestPath, $data);
             return response()->json( ["Cache-Status" => "MISS", 'data'=> $data], 200);
            } catch (Exception $e) {
            return response()->json(["error" => "Failed to get Book Info from catalog service due to an internal error."], 500);
            }
    }

    protected function purchase($requestPath)
    {
        try {
            $orderUrl = $this->getNextOrderUrl();
            $response = $this->client->post("{$orderUrl}/{$requestPath}");
            return response()->json(json_decode($response->getBody()), 200);
        } catch (Exception $e) {
            return response()->json(["error" => "Failed to process purchase with order service due to an internal error."
        ,'error' => $e->getMessage()], 500);
        }
    }

    protected function order($requestPath)
    {
        try {
            $catalogUrl = $this->getNextCatalogUrl();
            $response = $this->client->post("{$catalogUrl}/{$requestPath}");
            return response()->json(json_decode($response->getBody()), 200);
        } catch (Exception $e) {
            return response()->json(["error" => "Failed to process order/update with catalog service due to an internal error."], 500);
        }
    }

    protected function updateItem($requestPath)
    {
        try {
            $catalogUrl = $this->getNextCatalogUrl();
            $payload = request()->json()->all();
            $response = $this->client->put("{$catalogUrl}/{$requestPath}", [
                "json" => $payload 
            ]);
            if ($response){
            
                $this->invalidateCache($requestPath);
                return response()->json(json_decode($response->getBody()), 200);

            }
            return response()->json(json_decode($response->getBody()), 200);
        } catch (Exception $e) {
            return response()->json(["error" => "Failed to update item in catalog service due to an internal error."
            ,'error' => $e->getMessage()], 500);
        }
    }



    public function handleFront(Request $request)
    {
          $fullRequest = $request->getRequestUri(); 

        $cleanedRequest = str_replace('/client/', '', $fullRequest);
        
        try {
             $this->client = new Client(["timeout" => 10]);
        } catch (Exception $e) {
             return response()->json(["error" => "Failed to initialize HTTP client.",'error'=> $e->getMessage()], 500);
        }

        if ($request->isMethod("GET")) {
            if (str_starts_with($cleanedRequest, "catalog/search/")) {
                return $this->search($cleanedRequest);

            } elseif (str_starts_with($cleanedRequest, "catalog/item/")) {
                return $this->getBookInfo($cleanedRequest);
            }
        } elseif ($request->isMethod("POST")) {
            if (str_starts_with($cleanedRequest, "order/purchase/")) {
                return $this->purchase($cleanedRequest);
            } elseif (str_starts_with($cleanedRequest, "catalog/order/")) {
                return $this->order($cleanedRequest);
            }
        } elseif ($request->isMethod("PUT")) {
             if (str_starts_with($cleanedRequest, "catalog/book/")) {
                return $this->updateItem($cleanedRequest);
            }
        }

        return response()->json(["error" => "Service not found for the requested path and method."], 404);
    }


}
