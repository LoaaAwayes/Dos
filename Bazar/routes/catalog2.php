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


    $router->put('/replicate-order', 'Catalog2Controller@replicateOrder');
    $router->put('/replicate-update', 'Catalog2Controller@replicateUpdate');

$router->post('/order/{id}', 'Catalog2Controller@order');
$router->put('/order/{id}', 'Catalog2Controller@order');

$router->get('search/topic/{title}', 'Catalog2Controller@searchByTitle');
$router->get('item/{id}', 'Catalog2Controller@getItemDetails');
$router->put('book/{id}', 'Catalog2Controller@updateItem');
 
