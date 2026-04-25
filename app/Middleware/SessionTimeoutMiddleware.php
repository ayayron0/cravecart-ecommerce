<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;


/*
 * SessionTimeoutMiddleware — automatically logs out inactive users.
 *
 * WHAT: Checks how long it has been since the user last made a request.
 *       If the gap exceeds 30 minutes, the session is destroyed and the
 *       user is redirected to the login page with a timeout message.
 * HOW:  Slim calls __invoke() before every request on protected routes.
 *       It reads $_SESSION['last_activity'] and compares it to the current
 *       time. On each valid request it resets the timestamp so the timer
 *       only triggers after 30 minutes of true inactivity.
 * WHERE: Applied to both the /admin and /account route groups in web-routes.php.
 */
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
                $_SESSION['flash'] = [
                    'type' => 'warning',
                    'message' => 'Your session has timed out. Please login again.',
                ];
                // Builds the URL prefix for the app's subfolder (e.g. /cravecart-ecommerce).
                // Without this, the redirect would point to the server root instead of the app.
                $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';

                // Redirects to the login page and lets the flash message explain why.
                return (new SlimResponse())->withStatus(302)->withHeader('Location', $basePath . '/login');

            }
        }
        $_SESSION['last_activity'] = time();
        return $handler->handle($request);
    }
}
