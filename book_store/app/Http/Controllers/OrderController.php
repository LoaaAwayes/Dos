<?php

namespace App\Http\Controllers;

use PDO;  
use PDOException;  
use Exception;

class OrderController extends Controller
{
    public function purchase($id)
    {
        try {


            $pdo = new PDO('sqlite:database.db');

            

            //$databasePath = __DIR__ . '/../../../storage/database.db'; 
            //$pdo = new PDO("sqlite:$databasePath");

           // echo "Database file used: ".realpath('database.db');
            //die();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);// to handle exceptions

            $pdo->beginTransaction();

            $query = $pdo->prepare("SELECT numItemsInStock FROM bookCatalog WHERE id = ?");
            $query->execute([$id]);
            $book = $query->fetch(PDO::FETCH_ASSOC);
    
            if (!$book) {
                throw new Exception("Error: Item ID $id does not exist.");
            }
    
            if ($book['numItemsInStock'] <= 0) {
                throw new Exception("Purchase failed: Item ID $id is out of stock.");
            }

            // Insert the order (assume quantity is always 1)
            $insertOrder = $pdo->prepare("INSERT INTO orders (bookId, quantity) VALUES (?, 1)");
            $insertOrder->execute([$id]);

            // Decrement the book stock since purchace happened 
            $updateStock = $pdo->prepare("UPDATE bookCatalog SET numItemsInStock = numItemsInStock - 1 WHERE id = ?");
            $updateStock->execute([$id]);
            
            $pdo->commit();

            // additional if we want to view the quantity after selling on book  
            $selledBook = $pdo->prepare("SELECT * FROM bookCatalog  WHERE id = ?");
            $selledBook->execute([$id]);
            

            $book = $selledBook->fetch(PDO::FETCH_ASSOC);

            /*
            if ($book) {
            print_r($book); 
            } else {
            echo "No book found with ID: $id";
                   }
            */


            return response()->json([
                'message' => 'Order placed successfully'
            ], 200);


        } catch (Exception $e) {

            $pdo->rollBack();
            return json_encode(["error" => $e->getMessage()]);
        }
        catch (PDOException $e) {

            $pdo->rollBack();
            echo "here is the error";
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
}
