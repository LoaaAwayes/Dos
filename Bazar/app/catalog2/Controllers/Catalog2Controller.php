<?php

namespace App\catalog2\Controllers;

use Laravel\Lumen\Routing\Controller;
use PDO;
use PDOException;
use Exception;
use Illuminate\Http\Request;

class Catalog2Controller extends Controller
{

/*
this function checks for book befor ordering 
then when book exists it decreases its number in stock by one
then call function to notify the replica 
*/ 
public function order($id)
{
    try {
        $pdo = new PDO('sqlite:databaseCopy.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->beginTransaction();

        // Get current stock from primary database
        $query = $pdo->prepare("SELECT numItemsInStock, bookTitle, bookCost FROM bookCatalog WHERE id = ?");
        $query->execute([$id]);
        $book = $query->fetch(PDO::FETCH_ASSOC);

        // check if book in stock
        if (!$book) {
            throw new Exception("Error: Item ID $id does not exist.");
        }

        if ($book['numItemsInStock'] <= 0) {
            throw new Exception("Purchase failed: Item ID $id is out of stock.");
        }

        // Process order in database
        $insertOrder = $pdo->prepare("INSERT INTO orders (bookId, quantity) VALUES (?, 1)");
        $insertOrder->execute([$id]);

        $updateStock = $pdo->prepare("UPDATE bookCatalog SET numItemsInStock = numItemsInStock - 1 WHERE id = ?");
        $updateStock->execute([$id]);

        // Get updated stock from primary database
        $selledBook = $pdo->prepare("SELECT * FROM bookCatalog WHERE id = ?");
        $selledBook->execute([$id]);
        $book = $selledBook->fetch(PDO::FETCH_ASSOC);



        $pdo->commit();
       
        // Only replicate if the request doesn't come from another replica  this part is for testing
        if (!request()->header('Replicated')) {
            $replicaSent = $this->sendOrderReplication($id, $book['bookTitle'], $book['numItemsInStock'], $book['bookCost']);
        }

        return response()->json([
            'message' => 'Order placed successfully. Happy reading!',
            'book' => $book,
            //'book2' => $book2,
            'replicaMsg' => $replicaSent 
        ], 200);

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        if (isset($pdo2) && $pdo2->inTransaction()) $pdo2->rollBack();
        return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        if (isset($pdo2) && $pdo2->inTransaction()) $pdo2->rollBack();
        return response()->json(['error' => $e->getMessage()], 400);
    }
}

    // POST replication for orders

    /* 
    this function used in order function, to notify => send request to the replica to edit the stock quantity
    */
    protected function sendOrderReplication($id, $title, $quantity, $price)
    {
       $replicaUrl = "http://localhost:9001/catalog/replicate-order"; // Adjust according to your environment
        
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->put($replicaUrl, [
                'json' => [
                    'id' => $id,
                    'title' => $title,
                    'quantity' => $quantity,
                    'price' => $price
                ],
                'headers' => [
                    'Replicated' => 'true' // Mark this as a replication request// used for testing now not usefull
                ],
            'timeout' => 5 
            ]);

               return  response()->json(
                [
                    'id' => $id,
                    'title' => $title,
                    'quantity' => $quantity,
                    'price' => $price,
                    
                     'response' => json_decode($response->getBody(), true)
                ], 200);
        } catch (\Exception $e) {
            // Log the error silently
            error_log("Update replication failed: " . $e->getMessage());
        }
    }

    // PUT replication for updates  => notufy replica when update 
    protected function sendUpdateReplication($id, $title, $quantity, $price)
    {
        $replicaUrl = "http://localhost:9001/catalog/replicate-update"; 
        
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->put($replicaUrl, [
                'json' => [
                    'id' => $id,
                    'title' => $title,
                    'quantity' => $quantity,
                    'price' => $price
                ],
                'headers' => [
                    'Replicated' => 'true' // Mark this as a replication request
                ],
            'timeout' => 5 
            ]);

               return  response()->json(
                [
                    'id' => $id,
                    'title' => $title,
                    'quantity' => $quantity,
                    'price' => $price,
                    
                     'response' => json_decode($response->getBody(), true)
                ], 200);
        } catch (Exception $e) {
            // Log the error silently
            error_log("Update replication failed: " . $e->getMessage());
        }
    }
/* when totified by other server change current database to the coming data => when order item called */
    public function replicateOrder(Request $request)
    {

        try {
            $id = $request->input('id');
           
            $quantity = $request->input('quantity');
          

            $pdo = new PDO('sqlite:databaseCopy.db');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $pdo->beginTransaction();

            // Full update for update replication
            $updateQuery = $pdo->prepare("UPDATE bookCatalog SET numItemsInStock = ? WHERE id = ?");
            $updateQuery->execute([$quantity, $id]);

            $pdo->commit();

            return response()->json(['message' => 'Order replication successful'], 200);

        } catch (PDOException $e) {
            // if (isset($pdo) && $pdo->inTransaction()) {
            //     $pdo->rollBack();
            // }
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
/* when totified by other server change current database to the coming data => when update item called */

    public function replicateUpdate(Request $request)
    {
        try {
            $id = $request->input('id');
            $title = $request->input('title');
            $quantity = $request->input('quantity');
            $price = $request->input('price');

            $pdo = new PDO('sqlite:databaseCopy.db');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

           //$pdo->beginTransaction();

            // Full update for update replication
            $updateQuery = $pdo->prepare("UPDATE bookCatalog SET bookTitle = ?, numItemsInStock = ?, bookCost = ? WHERE id = ?");
            $updateQuery->execute([$title, $quantity, $price, $id]);

            //$pdo->commit();

            return response()->json(['message' => 'Update replication successful'], 200);

        } catch (PDOException $e) {
            // if (isset($pdo) && $pdo->inTransaction()) {
            //     $pdo->rollBack();
            // }
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
/* this method is for updating the item in stock => post request */
    public function updateItem($id)
    {
        try {
            $pdo = new PDO('sqlite:databaseCopy.db');
            //$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $existingItemQuery = $pdo->prepare("SELECT * FROM bookCatalog WHERE id = ?");
            $existingItemQuery->execute([$id]);
            $existingItem = $existingItemQuery->fetch(PDO::FETCH_ASSOC);

            if (!$existingItem) {
                return response()->json(['message' => 'Item not found.'], 404);
            }

            $title = request()->input('title');
            $quantity = request()->input('quantity');
            $price = request()->input('price');

            if (empty($title) || empty($quantity) || empty($price)) {
                return response()->json(['error' => 'All fields are required.'], 400);
            }

            if (!is_numeric($quantity) || !is_numeric($price)) {
                return response()->json(['error' => 'Quantity and price must be numeric values.'], 400);
            }

            $quantity = (int)$quantity;
            $price = (float)$price;

            $updateQuery = $pdo->prepare("UPDATE bookCatalog SET bookTitle = ?, numItemsInStock = ?, bookCost = ? WHERE id = ?");
            $updateQuery->execute([$title, $quantity, $price, $id]);

            if ($updateQuery->rowCount() === 0) {
                return response()->json(['message' => 'No changes made.'], 404);
            }

            $updatedItemQuery = $pdo->prepare("SELECT * FROM bookCatalog WHERE id = ?");
            $updatedItemQuery->execute([$id]);
            $updatedItem = $updatedItemQuery->fetch(PDO::FETCH_ASSOC);

            // Only replicate if the request doesn't come from another replica
            if (!request()->header('Replicated')) {
                $replicaSent = $this->sendUpdateReplication($id, $title, $quantity, $price);
            }

            return response()->json(['message' => 'Item updated successfully.', 'updated_item' => $updatedItem ,'replica msg:'=>$replicaSent], 200);

        } catch (PDOException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

/** search by topic function: url example:  http://localhost:9000/client/search/topic/undergraduate school */ 
    public function searchByTitle($title)
    {
        try {
            
            $pdo = new PDO('sqlite:databaseCopy.db');
            //$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
           // echo "Database file used: ".realpath('database.db');
           // die();
            
    
            $title = urldecode($title);
            $title = trim($title);

            //Log::info("Searching for book with title: " . $title);

            $searchQuery = $pdo->prepare("SELECT * FROM bookCatalog WHERE bookTopic LIKE ?");
            $searchQuery->execute(['%' . $title . '%']); 
          
            $books = $searchQuery->fetchAll(PDO::FETCH_ASSOC);

          
            if (empty($books)) {
                /*
                $allBooksQuery = $pdo->prepare("SELECT * FROM bookCatalog");
                $allBooksQuery->execute();
                $books = $allBooksQuery->fetchAll(PDO::FETCH_ASSOC);*/

                return response()->json(['message' => 'topic not found.'], 404);
            }

           
            return response()->json($books, 200);

        } catch (PDOException $e) {
        
            //Log::error("Error occurred during search: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
/* get item details url: http://localhost:9000/client/item/3 */
    public function getItemDetails($id)
    {
        try {
           
            $pdo = new PDO('sqlite:databaseCopy.db');
            //$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 



            
            $itemQuery = $pdo->prepare("SELECT * FROM bookCatalog WHERE id = ?");
            $itemQuery->execute([$id]);

            
            $item = $itemQuery->fetch(PDO::FETCH_ASSOC);

            
            if (!$item) {
                return response()->json(['message' => 'Item not found.'], 404);
            }

          
            return response()->json($item, 200);

        } catch (PDOException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
