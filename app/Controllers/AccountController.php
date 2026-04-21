<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use App\Domain\Models\Users;
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
    // TODO: replace dummy data with Orders::findByUser($_SESSION['user_id'])
    public function showOrders(Request $request, Response $response, array $args): Response
    {
        // --- DUMMY DATA (replace with DB later) ---
        $data['orders'] = [
            ['id' => 1001, 'items' => 'Kung Pao Chicken, Spring Rolls', 'total' => '24.50', 'status' => 'delivered', 'created_at' => 'Apr 18, 2026'],
            ['id' => 1002, 'items' => 'Sushi Platter, Miso Soup',      'total' => '38.00', 'status' => 'shipped',   'created_at' => 'Apr 19, 2026'],
            ['id' => 1003, 'items' => 'Pad Thai, Bubble Tea',          'total' => '19.75', 'status' => 'wrapping',  'created_at' => 'Apr 20, 2026'],
        ];
        // --- END DUMMY DATA ---

        $data['activeNav'] = 'orders';
        return $this->render($response, 'Account/orders.twig', $data);
    }

    // Renders the profile form pre-filled with the client's current info.
    // Shows a success banner if redirected here after a successful update (?updated=1).
    public function showProfile(Request $request, Response $response, array $args): Response
    {
        $data['user']      = Users::findById((int) $_SESSION['user_id']);
        $data['activeNav'] = 'profile';

        if (($request->getQueryParams()['updated'] ?? null) === '1') {
            $data['updated'] = true;
        }

        return $this->render($response, 'Account/profile.twig', $data);
    }

    // Handles the profile form submission — updates name/email and optionally password.
    public function updateProfile(Request $request, Response $response, array $args): Response
    {
        $body     = $request->getParsedBody();
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        $errors   = [];

        $name  = trim($body['name']  ?? '');
        $email = trim($body['email'] ?? '');

        if (empty($name) || empty($email)) {
            $errors[] = 'Name and email cannot be empty.';
        } else {
            Users::update((int) $_SESSION['user_id'], $name, $email);
            // Keep the session name in sync so the sidebar shows the updated name immediately.
            $_SESSION['name'] = $name;
        }

        // Only attempt a password change if the user filled in at least one password field.
        $current = $body['current_password'] ?? '';
        $new     = $body['new_password']     ?? '';
        $confirm = $body['confirm_password'] ?? '';

        if (!empty($current) || !empty($new) || !empty($confirm)) {
            if (strlen($new) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif ($new !== $confirm) {
                $errors[] = 'New passwords do not match.';
            } elseif (!Users::updatePassword((int) $_SESSION['user_id'], $current, $new)) {
                $errors[] = 'Current password is incorrect.';
            }
        }

        if (!empty($errors)) {
            $data['user']      = Users::findById((int) $_SESSION['user_id']);
            $data['errors']    = $errors;
            $data['activeNav'] = 'profile';
            return $this->render($response, 'Account/profile.twig', $data);
        }

        // Redirect back to GET so a page refresh doesn't re-submit the form.
        return $response->withStatus(302)->withHeader('Location', $basePath . '/account/profile?updated=1');
    }
}
