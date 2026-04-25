<?php

declare(strict_types=1);

/*
 * web-routes.php — all URL routes for the application
 *
 * WHAT: Maps every URL the app responds to, to the controller method that handles it.
 * HOW:  Each route defines an HTTP method (GET/POST), a URL pattern, and a controller.
 *       When a request comes in, Slim matches the URL against this list and calls
 *       the matching controller method.
 *
 * Route naming pattern: [controller].[method] (e.g. admin.orders, auth.login)
 * Named routes let you generate URLs in code without hardcoding strings.
 *
 * Route types used here:
 *   GET  — user is just loading a page (no data submitted)
 *   POST — user submitted a form (data is being sent to the server)
 */

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\BrowseController;
use App\Controllers\AdminController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\AccountController;
use App\Middleware\AdminMiddleware;
use App\Middleware\AccountMiddleware;
use App\Middleware\SessionTimeoutMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


return static function (Slim\App $app): void {


    //* NOTE: Route naming pattern: [controller_name].[method_name]
    $app->get('/', [HomeController::class, 'index'])
        ->setName('home.index');

    $app->get('/home', [HomeController::class, 'index'])
        ->setName('home.index');

    // AJAX endpoint used by the live search bar — returns JSON, not a page.
    $app->get('/search', [HomeController::class, 'search'])
        ->setName('home.search');

    // Static about page linked from the footer.
    $app->get('/about', [HomeController::class, 'about'])
        ->setName('home.about');

    //login route
    // Shows the login form
    $app->get('/login', [AuthController::class, 'showLogin'])
    ->setName('auth.login');
    
    //handles the login post request
    $app->post('/login', [AuthController::class, 'login'])->setName('auth.login.post');
    
    
    // Shows the register form
    $app->get('/register', [AuthController::class, 'showRegister'])
    ->setName('auth.register');

    //handles the register post request
    $app->post('/register', [AuthController::class, 'register'])->setName('auth.register.post');

    
    // Browse dishes by category and cuisine (e.g. /browse/food/chinese)
    $app->get('/browse/{category}/{slug}', [BrowseController::class, 'showDishes'])
    ->setName('browse.dishes');
    
    // Admin routes
   $app->group('/admin', function ($group) {
        $group->get('/orders',          [AdminController::class, 'orders'])->setName('admin.orders');
        $group->post('/orders/{id}/status', [AdminController::class, 'updateOrderStatus'])->setName('admin.orders.status');
        $group->get('/menu',            [AdminController::class, 'menu'])->setName('admin.menu');
        $group->get('/add',             [AdminController::class, 'showAdd'])->setName('admin.add');
        $group->post('/add/cuisine',    [AdminController::class, 'addCuisine'])->setName('admin.add.cuisine');
        $group->post('/add/category',   [AdminController::class, 'addCategory'])->setName('admin.add.category');
        $group->post('/add/dish',       [AdminController::class, 'addDish'])->setName('admin.add.dish');
        $group->get('/edit/dish/{id}', [AdminController::class, 'showEditDish'])->setName('admin.edit.dish');
        $group->post('/edit/dish/{id}', [AdminController::class, 'updateDish'])->setName('admin.update.dish');
        $group->get('/edit/cuisine/{id}', [AdminController::class, 'showEditCuisine'])->setName('admin.edit.cuisine');
        $group->post('/edit/cuisine/{id}', [AdminController::class, 'updateCuisine'])->setName('admin.update.cuisine');
        $group->get('/edit/category/{id}', [AdminController::class, 'showEditCategory'])->setName('admin.edit.category');
        $group->post('/edit/category/{id}', [AdminController::class, 'updateCategory'])->setName('admin.update.category');
        $group->get('/profile',         [AdminController::class, 'showProfile'])->setName('admin.profile');
        $group->post('/profile',        [AdminController::class, 'updateProfile'])->setName('admin.profile.update');
        $group->post('/delete/dish/{id}',     [AdminController::class, 'deleteDish'])->setName('admin.delete.dish');
        $group->post('/delete/cuisine/{id}',  [AdminController::class, 'deleteCuisine'])->setName('admin.delete.cuisine');
        $group->post('/delete/category/{id}', [AdminController::class, 'deleteCategory'])->setName('admin.delete.category');
    })->add(new AdminMiddleware())->add(new SessionTimeoutMiddleware());

    // Account routes
    $app->group('/account', function ($group) {
        $group->get('/orders',  [AccountController::class, 'showOrders'])->setName('account.orders');
        $group->get('/profile', [AccountController::class, 'showProfile'])->setName('account.profile');
        $group->post('/profile', [AccountController::class, 'updateProfile'])->setName('account.profile.update');
        $group->post('/delete', [AccountController::class, 'deleteAccount'])->setName('account.delete');
    })->add(new AccountMiddleware())->add(new SessionTimeoutMiddleware());

    // 2FA verification
    $app->get('/verify-2fa',  [AuthController::class, 'showVerify2fa'])->setName('auth.verify2fa');
    $app->post('/verify-2fa', [AuthController::class, 'verify2fa'])->setName('auth.verify2fa.post');

    //log out route
    $app->get('/logout', [AuthController::class, 'logout'])->setName('auth.logout');
    

    // Cart & checkout
    $app->get('/cart',      [CartController::class,     'showCart'])->setName('cart.show');
    $app->get('/checkout',  [CheckoutController::class, 'showCheckout'])->setName('checkout.show');

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
