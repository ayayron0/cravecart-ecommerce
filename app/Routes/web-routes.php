<?php

declare(strict_types=1);

/**
 * This file contains the routes for the web application.
 */

use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\DishController;
use App\Controllers\HomeController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


return static function (Slim\App $app): void {

    //* NOTE: Route naming pattern: [controller_name].[method_name]
    $app->get('/', [HomeController::class, 'index'])
        ->setName('home.index');

    $app->get('/home', [HomeController::class, 'index'])
        ->setName('home.index');

    // Browse: category → cuisines → dishes
    $app->get('/browse/{category}',           [DishController::class, 'cuisines'])
        ->setName('dish.cuisines');
    $app->get('/browse/{category}/{cuisine}', [DishController::class, 'dishes'])
        ->setName('dish.dishes');

    // Auth
    $app->get('/login',    [AuthController::class, 'loginForm'])
        ->setName('auth.login');
    $app->post('/login',   [AuthController::class, 'login'])
        ->setName('auth.login.post');
    $app->get('/register', [AuthController::class, 'registerForm'])
        ->setName('auth.register');
    $app->post('/register',[AuthController::class, 'register'])
        ->setName('auth.register.post');
    $app->get('/logout',   [AuthController::class, 'logout'])
        ->setName('auth.logout');

    // Cart
    $app->get('/cart', [CartController::class, 'index'])
        ->setName('cart.index');

    // A route to display PHP configuration information.
    $app->get('/phpinfo', function (Request $request, Response $response, $args) {
        ob_start();
        phpinfo();
        $phpinfo = ob_get_clean();
        $response->getBody()->write($phpinfo);
        return $response;
    });

    // A route to test runtime error handling and custom exceptions.
    $app->get('/error', function (Request $request, Response $response, $args) {
        throw new \Slim\Exception\HttpBadRequestException($request, "This is a runtime error. Something went wrong");
    });
};
