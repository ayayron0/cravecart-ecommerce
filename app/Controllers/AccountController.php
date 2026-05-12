<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\Notifications;
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

        // Converts the database dish name into a translation catalog slug key.
        // e.g. "Pad Thai" → "pad-thai", "Salmon Roll (8 pcs)" → "salmon-roll"
        // Needed because the catalog keys use kebab-case slugs but the DB stores full display names.
        $toSlug = static function (string $name): string {
            $s = preg_replace('/\s*\([^)]*\)/', '', $name);
            $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
            $s = strtolower(trim((string) $s));
            $s = preg_replace('/[^a-z0-9]+/', '-', $s);
            return trim((string) $s, '-');
        };

        $mapped = array_map(function ($order) use ($toSlug): array {
            $items = OrderDish::findDetailedByOrderId((int) $order->id);

            $itemNames = array_map(
                static function (array $item) use ($toSlug): string {
                    $key  = 'dishes_names.' . $toSlug($item['dish_name']);
                    $name = has_translation($key) ? __($key) : $item['dish_name'];
                    $qty  = (int) $item['quantity'];
                    return $qty > 1 ? "{$name} x{$qty}" : $name;
                },
                $items
            );

            $status = match (strtolower((string) $order->status)) {
                'pending'     => 'processing',
                'in progress' => 'wrapping',
                default       => strtolower((string) $order->status),
            };

            return [
                'id'           => $order->id,
                'order_number' => $order->id,
                'items'      => empty($itemNames) ? __('admin.no_items_found') : implode(', ', $itemNames),
                'total'      => number_format((float) $order->total, 2),
                'status'     => $status,
                'created_at' => date('M j, Y', strtotime((string) $order->ordered_at)),
            ];
        }, $orderBeans);

        $activeOrders  = array_values(array_filter($mapped, fn($o) => !in_array($o['status'], ['completed', 'cancelled'])));
        $historyOrders = array_values(array_filter($mapped, fn($o) => in_array($o['status'], ['completed', 'cancelled'])));

        return $this->render($response, 'Account/orders.twig', [
            'active_orders'  => $activeOrders,
            'history_orders' => $historyOrders,
            'tab'            => $request->getQueryParams()['tab'] ?? 'active',
            'activeNav'      => 'orders',
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
        $this->flash('success', __('account.profile_updated'));
        return $this->redirectTo($response, '/account/profile');
    }

    public function confirmDelivery(Request $request, Response $response, array $args): Response
    {
        $orderId = (int) ($args['id'] ?? 0);
        $userId  = (int) $_SESSION['user_id'];

        $order = Orders::findById($orderId);

        if ($order === null || (int) $order->user_id !== $userId || $order->status !== 'delivered') {
            $this->flash('danger', __('account.unable_to_confirm'));
            return $this->redirectTo($response, '/account/orders');
        }

        Orders::updateStatus($orderId, 'completed');
        $this->flash('success', __('account.confirm_order'));
        return $this->redirectTo($response, '/account/orders');
    }

    public function deleteAccount(Request $request, Response $response, array $args): Response
    {
        if (!Users::delete((int) $_SESSION['user_id'])) {
            $this->flash('danger', __('account.cannot_delete'));
            return $this->redirectTo($response, '/account/profile');
        }

        session_unset();
        $this->flash('success', __('account.account_deleted'));
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . '/');
    }

    public function cancelOrder(Request $request, Response $response, array $args): Response
    {
        $orderId = (int) $args['id'];

        $order = Orders::findById($orderId);

        if ($orderId > 0 && $order !== null) {
            
            if($order->user_id == $_SESSION['user_id']) {

                if($order->status === 'pending'){

                    Orders::updateStatus($orderId, 'cancelled');
                    Notifications::create((int) $order->user_id, "notifications.order_cancelled:{$orderId}");
                    $this->flash('success', __('account.order_cancelled'));
                }else{
                    $this->flash('danger', __('account.unable_to_cancel'));
                }
            }
        }

        return $this->redirectTo($response, '/account/orders');
    }

    // Small redirect helper so we do not repeat base path logic.
    private function redirectTo(Response $response, string $path): Response
    {
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . $path);
    }
}
