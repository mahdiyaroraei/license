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
$app->post('checkupdate' , 'AppsController@checkupdate');
$app->post('buy' , 'BuyController@buy');
$app->get('pay/{license_id}/{discount}' , 'BuyController@pay');
$app->get('pay/{license_id}/' , 'BuyController@paying');
$app->get('payed/{license_id}/{discount}' , 'BuyController@payed');
$app->get('payed/{license_id}' , 'BuyController@payed');
$app->post('activation' , 'BuyController@activation');
$app->post('deactive' , 'BuyController@deactive');
$app->get('generatecode' , 'BuyController@generatecode');
$app->post('resendcode' , 'BuyController@resendcode');
$app->post('checklicense' , 'BuyController@checklicense');

$app->get('asd' , 'BuyController@checkCountry');

$app->get('invitations/{id}' , 'BuyController@invitations');
$app->get('gift/{id}/{license_id}/{user_id}' , 'BuyController@gift');

// Download
$app->get('install' , 'BuyController@install');
$app->get('install/android' , 'BuyController@installAndroid');

// Push
$app->post('user/{license_id}/{player_id}' , 'BuyController@update_player_id');

//V2

$app->post('v2/buy' , 'BuyController@buy_v2');
$app->post('v2/activation' , 'BuyController@activation_v2');
$app->post('v2/login' , 'BuyController@login');

// Articles
$app->get('articles' , 'ArticleController@articles');
$app->get('article/{id}/{license_id}/{user_id}' , 'ArticleController@article');
$app->get('article/{id}' , 'ArticleController@articleView');
$app->get('view/{id}' , 'ArticleController@view');
$app->get('clapping/{id}/{amount}' , 'ArticleController@clapping');

// Categories
$app->get('categories' , 'ArticleController@categories');
$app->get('articles/{cat_id}' , 'ArticleController@articlesFromCategory');

// Comments
$app->post('comment/{article_id}/{license_id}' , 'ArticleController@comment');
$app->get('comments/{article_id}' , 'ArticleController@commentsOfArticle');

// Subscribe
$app->get('subscribe/{license_id}/{user_id}' , 'BuyController@hassubscribe');

// sells
$app->get('sells/{app_id}' , 'BuyController@sells');
$app->post('setdiscount' , 'BuyController@discount');
$app->get('discount' , 'BuyController@discountList');


////////////////////////////////////// Topics Routing //////////////////////////////////////
$app->post('message' , 'TopicsController@addMessage');
$app->post('topic' , 'TopicsController@addTopic');
$app->post('userinfo' , 'TopicsController@updateUserInformation');
$app->get('topics/{offset}' , 'TopicsController@listAllTopics');
$app->get('messages/{topic_id}/{offset}' , 'TopicsController@listTopicMessages');

