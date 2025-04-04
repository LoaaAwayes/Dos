<?php 

//define PDO - tell about the database file
$pdo = new PDO('sqlite:../../public/database.db');


//write SQL
$statement = $pdo->query("SELECT * FROM bookCatalog");//C:\Users\Dell\Desktop\NNU\DOS\docker\Dos\book_store\public\database.db

//run the SQL
$rows = $statement->fetchAll(PDO :: FETCH_ASSOC);

//show it on the screen
echo "<pre>";
print_r($rows);
echo "</pre>";

$statement = $pdo->query("SELECT * FROM orders");

//run the SQL
$rows = $statement->fetchAll(PDO :: FETCH_ASSOC);

//show it on the screen
echo "<pre>";
print_r($rows);
echo "</pre>";

