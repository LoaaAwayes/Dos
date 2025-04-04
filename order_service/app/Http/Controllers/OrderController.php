<?php

namespace App\Http\Controllers;

use PDO;  
//use PDOException;  
use Exception;

use GuzzleHttp\Client;

class OrderController extends Controller
{
    public function purchase($id)
    {
        try {
            $client = new Client(['timeout' => 20]);

 /*           $order = [
                'book_id' => $id,
                'quantity' => 1,
            ];
*/
            // Send POST request to catalog server
            $response = $client->post("http://localhost:8001/order/{$id}");
            
            

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
                'message' => 'The book ordered is successfully, Happy reading',
                'response' => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 555);
        }
    }
}
