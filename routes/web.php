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

/**
 * User registration, login and logout routes:
 */
$router->group(['prefix' => 'user'], function() use ($router)
{
    $router->post('register', 'AuthController@register');
    $router->get('register/verification/{token}', 'AuthController@verifyEmail');
    $router->post('logout', 'AuthController@logout');
    $router->post('login', 'AuthController@authenticate');
    $router->post('login/verifySms', 'AuthController@verifySms');
    /**
     * Forgot Password feature Routes:
     */
    $router->post('/password/email', 'PasswordController@postEmail');
    $router->post('/password/reset/', [
        'as' => 'password.reset', 'uses' => 'PasswordController@postReset'
    ]);
});




/**
 * One Time Password 2FA Feature Test, routes:
 */
$router->group(['prefix' => 'v1/otp'], function () use ($router)
{
    $router->post('send', 'OTPController@sendSms');
    $router->post('verify', 'OTPController@verifySms');
});

$router->group(['middleware' => 'jwt-auth' ], function($router)  {
    // Protected route...
    $router->get('test', function (Request $request) use ($router) {
        return "Authenticated data - ceritanya.";
    });
});

// $router->group(['middleware' => 'jukirAuth', 'prefix'=>'coba' ], function($router)  {
//     // Protected route...
//     $router->get('register', '');
// });