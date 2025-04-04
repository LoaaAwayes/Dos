<?php
namespace App\Http\Controllers;



//use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;
use Exception;


class CatalogController extends Controller
{

    public function order($id)
    {
        try {


            $pdo = new PDO('sqlite:database.db');
            //$databasePath = __DIR__ . '/../../../storage/database.db'; 
            //$pdo = new PDO("sqlite:$databasePath");

            //echo "Database file used: ".realpath('database.db');
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
                'message' => 'Order placed successfully',$book
            ], 200);


        } catch (Exception $e) {

           // $pdo->rollBack();
            return json_encode(["error" => $e->getMessage()]);
        }
        catch (PDOException $e) {

            //$pdo->rollBack();
            echo "here is the error";
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function searchByTitle($title)
    {
        try {
            
            $pdo = new PDO('sqlite:database.db');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
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

    public function getItemDetails($id)
    {
        try {
           
            $pdo = new PDO('sqlite:database.db');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 



            
            $itemQuery = $pdo->prepare("SELECT * FROM bookCatalog WHERE id = ?");
            $itemQuery->execute([$id]);

            
            $item = $itemQuery->fetch(PDO::FETCH_ASSOC);

            
            if (!$item) {
                return response()->json(['message' => 'Item not found.'], 404);
            }

          
            return response()->json($item, 200);

        } catch (PDOException $e) {
            //Log::error("Error occurred during item details fetch: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateItem($id)
    {
        try {
           
            $pdo = new PDO('sqlite:database.db');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

           
            $existingItemQuery = $pdo->prepare("SELECT * FROM bookCatalog WHERE id = ?");
            $existingItemQuery->execute([$id]);
            $existingItem = $existingItemQuery->fetch(PDO::FETCH_ASSOC);
    
        
            if (!$existingItem) {
                return response()->json(['message' => 'Item not found.'], 404);
            }
    
            
            //Log::info("Existing item before update: " . json_encode($existingItem));
    
            
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
    
            //Log::info("Updated item after update: " . json_encode($updatedItem));
    
            
            return response()->json(['message' => 'Item updated successfully.', 'updated_item' => $updatedItem], 200);
    
        } catch (PDOException $e) {
            
            //Log::error("Error occurred during update: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}