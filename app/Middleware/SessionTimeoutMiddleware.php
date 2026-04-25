<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;


class SessionTimeoutMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        if(isset($_SESSION['last_activity']))
        {
            $time = time() - $_SESSION['last_activity'];
            if($time > 1800) // 30 minutes
            {
                session_unset();
                session_destroy();
                // Builds the URL prefix for the app's subfolder (e.g. /cravecart-ecommerce).
                // Without this, the redirect would point to the server root instead of the app.
                $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';

                // Redirects to the login page with timeout=1 so showLogin method in authcontroller knows to display the timeout message.
                return (new SlimResponse())->withStatus(302)->withHeader('Location', $basePath . '/login?timeout=1');

            }
        }
        $_SESSION['last_activity'] = time();
        return $handler->handle($request);
    }
}
