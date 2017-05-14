<?php

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

$app->get('/', function () use ($app) {
    return $app->version();
});

$app->post('apps' , 'AppsController@add');
$app->post('buy' , 'BuyController@buy');
$app->get('pay/{license_id}' , 'BuyController@pay');
$app->get('payed/{license_id}' , 'BuyController@payed');
$app->post('activation' , 'BuyController@activation');
$app->post('deactive' , 'BuyController@deactive');
$app->get('generatecode' , 'BuyController@generatecode');
$app->post('freeactivation' , 'BuyController@freeactivation');