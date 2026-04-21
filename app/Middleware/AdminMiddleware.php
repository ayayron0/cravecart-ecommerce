<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/*
 * WHAT:  A security checkpoint that protects admin-only pages.
 * HOW:   Slim calls __invoke() automatically before the controller runs.
 *        It checks the session to see if the user is logged in as an administrator.
 *        If yes, the request continues to the controller.
 *        If no, the user is redirected to the login page.
 * WHY:   Without this, anyone could type /admin/orders in the browser and get in.
 */
class AdminMiddleware
{
    // __invoke() is a special PHP method that lets a class be called like a function.
    // Slim 4 middleware receives the request and a $handler (the next layer in the pipeline).
    // $handler replaces the old $response + $next pattern from Slim 3.
    public function __invoke(Request $request, Handler $handler): Response
    {
        // Check if a session exists and the user's role is administrator.
        // isset() prevents errors if $_SESSION['user_id'] was never set.
        // The || means: block access if EITHER condition is true.
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'administrator') {
            // Not an admin — create a fresh response and redirect to login.
            $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
            return (new SlimResponse())
                ->withStatus(302)
                ->withHeader('Location', $basePath . '/login');
        }

        // User is an admin — pass the request through to the controller.
        return $handler->handle($request);
    }
}