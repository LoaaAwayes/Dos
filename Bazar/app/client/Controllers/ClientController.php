<?php
namespace App\client\Controllers;

use Laravel\Lumen\Routing\Controller;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Exception;

class ClientController extends Controller
{
    protected $client;
    protected $catalogServiceUrl = 'http://catalog_service:8000'; 
    protected $orderServiceUrl = 'http://order_service:8000';  



        // Search for books by title
        protected function search($request)
        {
            try {
                $response = $this->client->get("{$this->catalogServiceUrl}/{$request}");
                return response()->json(json_decode($response->getBody()), 200);
            } catch (Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }

        protected function order($request)
        {
            try {
                $response = $this->client->post("{$this->catalogServiceUrl}/{$request}");
                return response()->json(json_decode($response->getBody()), 200);
            } catch (Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }
    
        // Get book details by ID
        protected function getBookInfo($request)
        {
            try {
                $response = $this->client->get("{$this->catalogServiceUrl}/{$request}");
                return response()->json(json_decode($response->getBody()), 200);
            } catch (Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }
    
        // Purchase a book
        protected function purchase($request)
        {
            try {
                $response = $this->client->post("{$this->orderServiceUrl}/{$request}");
                return response()->json(json_decode($response->getBody()), 200);
            } catch (Exception $e) {
                return response()->json(['error' => $e->getMessage()], 525);
            }
        }
    
        protected function updateItem( $request)
    {
        try {
            $response = $this->client->put("{$this->catalogServiceUrl}/{$request}");
    
            return response()->json(json_decode($response->getBody()), 200);
        } catch (Exception $e) {
            echo"error inside client controller in uptade";
            return response()->json(['error' => $e->getMessage()], 520);
        }
    }

    public function handleFront(Request $request)
    {
       
        $fullRequest = $request->getRequestUri(); 

        $cleanedRequest = str_replace('/client/', '', $fullRequest);

       //return response()->json(['full_request' => $cleanedRequest]);
       


        $this->client = new Client(['timeout' => 10]);

        if (str_starts_with($cleanedRequest, 'purchase/')) {

            return $this->purchase($cleanedRequest);

        } elseif (str_starts_with($cleanedRequest, 'order/')) {

            return $this->order($cleanedRequest);

        } elseif (str_starts_with($cleanedRequest, 'search/topic/')) {

            return $this->search($cleanedRequest);

        } elseif (str_starts_with($cleanedRequest, 'item/')) {
            //echo "item here";

            return $this->getBookInfo($cleanedRequest);
            

        } elseif (str_starts_with($cleanedRequest, 'book/')) {

           return $this->updateItem($cleanedRequest);

        }else{

            echo"no such service >> from clientcontroller file";
        }


    }



}
