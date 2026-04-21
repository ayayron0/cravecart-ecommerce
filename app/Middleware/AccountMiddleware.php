<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/*
 * AccountMiddleware — protects all /account/* routes
 *
 * WHAT: Blocks unauthenticated users from accessing client account pages.
 * HOW:  Slim calls __invoke() before the controller runs. If no session
 *       exists the user is redirected to /login. Otherwise the request
 *       continues to the controller unchanged.
 */
class AccountMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        if (!isset($_SESSION['user_id'])) {
            $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
            return (new SlimResponse())->withStatus(302)->withHeader('Location', $basePath . '/login');
        }

        return $handler->handle($request);
    }
}
