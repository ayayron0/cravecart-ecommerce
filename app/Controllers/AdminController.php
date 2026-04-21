<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use App\Domain\Models\Users;
use App\Domain\Models\Cuisines;
use App\Domain\Models\Categories;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
 * AdminController — handles all admin panel pages
 *
 * WHAT: Controls the pages only administrators can access.
 * HOW:  Every route in the /admin group is protected by AdminMiddleware,
 *       which checks the session before this controller ever runs.
 *       Each method fetches the data needed for its page and renders the view.
 * NOTE: Currently uses dummy data — will be replaced with DB queries later.
 */
class AdminController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    // Renders the orders dashboard — shows all customer orders and their status
    public function orders(Request $request, Response $response, array $args): Response
    {
        // --- DUMMY DATA (replace with DB later) ---
        $data['orders'] = [
            ['id' => 1001, 'customer_name' => 'Alice Johnson', 'items' => 'Kung Pao Chicken, Spring Rolls', 'total' => '24.50', 'status' => 'processing',  'created_at' => 'Apr 20, 2026 10:14 AM'],
            ['id' => 1002, 'customer_name' => 'Bob Smith',     'items' => 'Sushi Platter, Miso Soup',      'total' => '38.00', 'status' => 'wrapping',    'created_at' => 'Apr 20, 2026 10:32 AM'],
            ['id' => 1003, 'customer_name' => 'Clara Lee',     'items' => 'Tacos x3, Churros',             'total' => '19.75', 'status' => 'shipped',     'created_at' => 'Apr 20, 2026 09:55 AM'],
            ['id' => 1004, 'customer_name' => 'David Nguyen',  'items' => 'Pad Thai, Mango Sticky Rice',   'total' => '22.00', 'status' => 'delivered',   'created_at' => 'Apr 20, 2026 08:40 AM'],
        ];
        // --- END DUMMY DATA ---

        // activeNav tells the sidebar which link to highlight as active
        $data['activeNav'] = 'orders';
        return $this->render($response, 'Admin/orders.twig', $data);
    }

    // Renders the menu management page — admin can add, edit, and delete dishes
    public function menu(Request $request, Response $response, array $args): Response
    {
        // --- DUMMY DATA (replace with DB later) ---
        $data['cuisines'] = [
            ['id' => 1, 'name' => 'Chinese'],
            ['id' => 2, 'name' => 'Japanese'],
            ['id' => 3, 'name' => 'Mexican'],
        ];

        $data['categories'] = [
            ['id' => 1, 'name' => 'Food'],
            ['id' => 2, 'name' => 'Desserts'],
            ['id' => 3, 'name' => 'Drinks'],
        ];

        $data['dishes'] = [
            ['emoji' => '🍛', 'name' => 'Kung Pao Chicken',   'cuisine' => 'Chinese',  'category' => 'Food',     'price' => '14.99'],
            ['emoji' => '🍱', 'name' => 'Sushi Platter',      'cuisine' => 'Japanese', 'category' => 'Food',     'price' => '22.00'],
            ['emoji' => '🌮', 'name' => 'Tacos',              'cuisine' => 'Mexican',  'category' => 'Food',     'price' => '9.50'],
            ['emoji' => '🍮', 'name' => 'Mango Sticky Rice',  'cuisine' => 'Japanese', 'category' => 'Desserts', 'price' => '7.00'],
            ['emoji' => '🧋', 'name' => 'Bubble Tea',         'cuisine' => 'Chinese',  'category' => 'Drinks',   'price' => '5.50'],
        ];
        // --- END DUMMY DATA ---

        //tell the sidebar which link to highlight as active
        $data['activeNav'] = 'menu';
        return $this->render($response, 'Admin/menu.twig', $data);
    }

    // Renders the profile page pre-filled with the admin's current info
    public function showProfile(Request $request, Response $response, array $args): Response
    {
        $user = Users::findById((int) $_SESSION['user_id']);

        $data['activeNav'] = 'profile';
        $data['user']      = $user;
        // Show success message if redirected here after a successful update
        if (($request->getQueryParams()['updated'] ?? null) === '1') {
            $data['updated'] = true;
        }
        return $this->render($response, 'Admin/profile.twig', $data);
    }

    // Handles the profile update form submission (email and/or password)
    public function updateProfile(Request $request, Response $response, array $args): Response
    {
        // Read submitted form data. ?? '' prevents errors if a field is missing.
        // trim() removes accidental spaces from the start and end of the input.
        $body     = $request->getParsedBody();
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        $errors   = [];

        // --- Update name and email ---
        $name  = trim($body['name']  ?? '');
        $email = trim($body['email'] ?? '');

        if (empty($name) || empty($email)) {
            $errors[] = 'Name and email cannot be empty.';
        } else {
            Users::update((int) $_SESSION['user_id'], $name, $email);
            // Keep the session in sync so the navbar shows the updated name immediately
            $_SESSION['name'] = $name;
        }

        // --- Update password (only runs if the user filled in at least one password field) ---
        $current  = $body['current_password']  ?? '';
        $new      = $body['new_password']       ?? '';
        $confirm  = $body['confirm_password']   ?? '';

        if (!empty($current) || !empty($new) || !empty($confirm)) {
            if (strlen($new) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif ($new !== $confirm) {
                // Both new password fields must match before we attempt the update
                $errors[] = 'New passwords do not match.';
            } elseif (!Users::updatePassword((int) $_SESSION['user_id'], $current, $new)) {
                // updatePassword() returns false if the current password is wrong
                $errors[] = 'Current password is incorrect.';
            }
        }

        // If there are any errors, re-render the form with the error messages
        if (!empty($errors)) {
            $user              = Users::findById((int) $_SESSION['user_id']);
            $data['user']      = $user;
            $data['errors']    = $errors;
            $data['activeNav'] = 'profile';
            return $this->render($response, 'Admin/profile.twig', $data);
        }

        // All updates succeeded — redirect back to profile with ?updated=1
        // The GET handler checks for that flag and shows the success message
        return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/profile?updated=1');
    }

    public function showAdd(Request $request, Response $response, array $args): Response
    {
        // Fetch real cuisines and categories from DB to populate the dropdowns
        $data['cuisines']   = Cuisines::getAll();
        $data['categories'] = Categories::getAll();
        $data['activeNav']  = 'add';

        return $this->render($response, 'Admin/add.twig', $data);
    }

    public function deleteDish(Request $request, Response $response, array $args): Response
    {
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        \RedBeanPHP\R::trash(\RedBeanPHP\R::load('dishes', (int) $args['id']));
        return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/menu');
    }

    public function deleteCuisine(Request $request, Response $response, array $args): Response
    {
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        \RedBeanPHP\R::trash(\RedBeanPHP\R::load('cuisines', (int) $args['id']));
        return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/add');
    }

    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        \RedBeanPHP\R::trash(\RedBeanPHP\R::load('categories', (int) $args['id']));
        return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/add');
    }
}



