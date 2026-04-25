<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\OrderDish;
use App\Domain\Models\Orders;
use App\Domain\Models\Users;
use App\Services\Validation\ProfileValidator;
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
    private ProfileValidator $profileValidator;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->profileValidator = new ProfileValidator();
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
    public function showProfile(Request $request, Response $response, array $args): Response
    {
        return $this->render($response, 'Account/profile.twig', [
            'user' => Users::findById((int) $_SESSION['user_id']),
            'activeNav' => 'profile',
        ]);
    }

    // Handles the profile form submission — updates name/email and optionally password.
    public function updateProfile(Request $request, Response $response, array $args): Response
    {
        $result = $this->profileValidator->validate($request->getParsedBody());
        $data = $result['data'];
        $errors = $result['errors'];

        $isChangingPassword =
            $data['current_password'] !== '' ||
            $data['new_password'] !== '' ||
            $data['confirm_password'] !== '';

        if (empty($errors) && $isChangingPassword) {
            if (!Users::verifyPassword((int) $_SESSION['user_id'], $data['current_password'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }

        if (empty($errors)) {
            Users::update((int) $_SESSION['user_id'], $data['name'], $data['email']);

            // Keep the session name in sync so the sidebar shows the updated name immediately.
            $_SESSION['name'] = $data['name'];

            // Change the password only after the current password has been verified.
            if ($isChangingPassword) {
                Users::updatePassword((int) $_SESSION['user_id'], $data['current_password'], $data['new_password']);
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
        $this->flash('success', 'Profile updated successfully.');
        return $this->redirectTo($response, '/account/profile');
    }

    public function deleteAccount(Request $request, Response $response, array $args): Response
    {
        if (!Users::delete((int) $_SESSION['user_id'])) {
            $this->flash('danger', 'We could not delete your account right now. Please try again.');
            return $this->redirectTo($response, '/account/profile');
        }

        session_unset();
        $this->flash('success', 'Your account has been deleted.');
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
