<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});


    $router->put('/replicate-order', 'CatalogController@replicateOrder');
    $router->put('/replicate-update', 'CatalogController@replicateUpdate');

$router->post('/order/{id}', 'CatalogController@order');
$router->put('/order/{id}', 'CatalogController@order');

$router->get('search/topic/{title}', 'CatalogController@searchByTitle');
$router->get('item/{id}', 'CatalogController@getItemDetails');
$router->put('book/{id}', 'CatalogController@updateItem');
 
