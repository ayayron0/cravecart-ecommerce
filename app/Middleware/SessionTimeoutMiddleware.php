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
 *       If the gap exceeds the timeout, the session is cleared and the
 *       user is redirected to the login page with a timeout message.
 * HOW:  Runs globally on every request. Only triggers for logged-in users.
 *       Resets the timer on each valid request (sliding window).
 */
class SessionTimeoutMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        $timeout = 1800; // 30 minutes

        if (isset($_SESSION['user_id'])) {
            if (!isset($_SESSION['last_activity'])) {
                $_SESSION['last_activity'] = time();
            } elseif (time() - $_SESSION['last_activity'] > $timeout) {
                $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
                $_SESSION = [
                    'flash' => [
                        'type'    => 'warning',
                        'message' => 'Your session has timed out. Please login again.',
                    ],
                ];
                return (new SlimResponse())->withStatus(302)->withHeader('Location', $basePath . '/login');
            }
            $_SESSION['last_activity'] = time();
        }

        return $handler->handle($request);
    }
}
