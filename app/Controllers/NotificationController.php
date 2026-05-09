<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\Notifications;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class NotificationController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    // GET /api/notifications — returns unread count + list for the logged-in user.
    public function index(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            $response->getBody()->write(json_encode(['count' => 0, 'notifications' => []]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $notifications = Notifications::findUnreadByUserId((int) $userId);

        $response->getBody()->write(json_encode([
            'count'         => count($notifications),
            'notifications' => $notifications,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    // POST /api/notifications/read — marks all unread notifications as read.
    public function markRead(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId) {
            Notifications::markAllReadByUserId((int) $userId);
        }

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
