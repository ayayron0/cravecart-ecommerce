<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\OrderDish;
use App\Domain\Models\Orders;
use App\Domain\Models\Users;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
 * AccountController — handles all client account pages (/account/*)
 *
 * WHAT: Controls pages that logged-in clients can access.
 * HOW:  Every route in the /account group is protected by AccountMiddleware,
 *       which checks the session before this controller ever runs.
 */
class AccountController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    // Renders the client's order history with a progress stepper per order.
    // Converts database orders into the exact shape Account/orders.twig expects.
    public function showOrders(Request $request, Response $response, array $args): Response
    {
        $orderBeans = Orders::findByUserId((int) $_SESSION['user_id']);

        $orders = array_map(function ($order): array {
            $items = OrderDish::findDetailedByOrderId((int) $order->id);

            $itemNames = array_map(
                static fn(array $item): string => $item['dish_name'],
                $items
            );

            // Map DB statuses to the UI labels used by the account order stepper.
            $status = match (strtolower((string) $order->status)) {
                'pending' => 'processing',
                'in progress' => 'wrapping',
                default => strtolower((string) $order->status),
            };

            return [
                'id' => $order->id,
                'items' => empty($itemNames) ? 'No items found' : implode(', ', $itemNames),
                'total' => number_format((float) $order->total, 2),
                'status' => $status,
                'created_at' => date('M j, Y', strtotime((string) $order->ordered_at)),
            ];
        }, $orderBeans);

        return $this->render($response, 'Account/orders.twig', [
            'orders' => $orders,
            'activeNav' => 'orders',
        ]);
    }

    // Renders the profile form pre-filled with the client's current info.
    // Shows a success banner if redirected here after a successful update (?updated=1).
    public function showProfile(Request $request, Response $response, array $args): Response
    {
        return $this->render($response, 'Account/profile.twig', [
            'user' => Users::findById((int) $_SESSION['user_id']),
            'activeNav' => 'profile',
            'updated' => (($request->getQueryParams()['updated'] ?? null) === '1'),
        ]);
    }

    // Handles the profile form submission — updates name/email and optionally password.
    public function updateProfile(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody();
        $errors = [];

        $name = trim($body['name'] ?? '');
        $email = trim($body['email'] ?? '');

        if ($name === '' || $email === '') {
            $errors[] = 'Name and email cannot be empty.';
        } else {
            Users::update((int) $_SESSION['user_id'], $name, $email);

            // Keep the session name in sync so the sidebar shows the updated name immediately.
            $_SESSION['name'] = $name;
        }

        // Only attempt a password change if the user filled in at least one password field.
        $current = $body['current_password'] ?? '';
        $new = $body['new_password'] ?? '';
        $confirm = $body['confirm_password'] ?? '';

        if ($current !== '' || $new !== '' || $confirm !== '') {
            if (strlen($new) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif ($new !== $confirm) {
                $errors[] = 'New passwords do not match.';
            } elseif (!Users::updatePassword((int) $_SESSION['user_id'], $current, $new)) {
                $errors[] = 'Current password is incorrect.';
            }
        }

        if (!empty($errors)) {
            return $this->render($response, 'Account/profile.twig', [
                'user' => Users::findById((int) $_SESSION['user_id']),
                'errors' => $errors,
                'activeNav' => 'profile',
            ]);
        }

        // Redirect back to GET so a page refresh doesn't re-submit the form.
        return $this->redirectTo($response, '/account/profile?updated=1');
    }

    public function deleteAccount(Request $request, Response $response, array $args): Response
    {
        Users::delete((int) $_SESSION['user_id']);
        session_unset();
        session_destroy();
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . '/');
    }

    // Small redirect helper so we do not repeat base path logic.
    private function redirectTo(Response $response, string $path): Response
    {
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . $path);
    }
}
