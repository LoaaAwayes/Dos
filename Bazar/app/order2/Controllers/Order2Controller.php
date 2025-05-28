<?php

namespace App\order2\Controllers;

use Laravel\Lumen\Routing\Controller;


//use PDOException;  
use Exception;

use GuzzleHttp\Client;

class Order2Controller extends Controller
{
     protected $client;
    public function purchase($id)
    {

      // return  response()->json('msg reached here order2 purchase');
        try {
            $client = new Client(['timeout' => 600]);

 /*           $order = [
                'book_id' => $id,
                'quantity' => 1,
            ];
*/
            // Send POST request to catalog server
            //$response = $client->post("http://catalog_service:8000/order/{$id}");
            $response = $client->post("http://localhost:9002/catalog2/order/{$id}");

            $data = json_decode($response->getBody(), true);



            





           /* $pdo = new PDO('sqlite:database.db');
            $selledBook = $pdo->prepare("SELECT * FROM bookCatalog  WHERE id = ?");
            $selledBook->execute([$id]);
            

            $book = $selledBook->fetch(PDO::FETCH_ASSOC);

            
            if ($book) {
            print_r($book); 
            } else {
            echo "No book found with ID: $id";
                   }
            */


            return response()->json([
                //'message' => 'The book ordered is successfully, Happy reading',
                'response' => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 555);
        }
    }
}
