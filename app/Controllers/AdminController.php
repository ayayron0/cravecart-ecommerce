<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use App\Domain\Models\Users;
use App\Domain\Models\Orders;
use App\Domain\Models\OrderDish;
use App\Domain\Models\Cuisines;
use App\Domain\Models\Categories;
use App\Domain\Models\Dishes;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
 * AdminController — handles all admin panel pages
 *
 * WHAT: Controls the pages only administrators can access.
 * HOW:  Every route in the /admin group is protected by AdminMiddleware,
 *       which checks the session before this controller ever runs.
 *       Each method fetches the data needed for its page and renders the view.
 * NOTE: Currently uses dummy data — will be replaced with DB queries later. (this has been done)
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
        $orderRows = Orders::findAllDetailed();

    $orders = array_map(function (array $order): array {
        $items = OrderDish::findDetailedByOrderId((int) $order['id']);

        $itemNames = array_map(
            static fn(array $item): string => $item['dish_name'],
            $items
        );

        $status = match (strtolower((string) $order['status'])) {
            'pending' => 'processing',
            'in progress' => 'wrapping',
            default => strtolower((string) $order['status']),
        };

        return [
            'id' => $order['id'],
            'customer_name' => $order['customer_name'],
            'items' => empty($itemNames) ? 'No items found' : implode(', ', $itemNames),
            'total' => number_format((float) $order['total'], 2),
            'status' => $status,
            'created_at' => date('M j, Y g:i A', strtotime((string) $order['ordered_at'])),
        ];
    }, $orderRows);

        $data['orders'] = $orders;
        $data['activeNav'] = 'orders';

        return $this->render($response, 'Admin/orders.twig', $data);

    }

    // Renders the menu management page — admin can add, edit, and delete dishes
    public function menu(Request $request, Response $response, array $args): Response
{
        $rows = Dishes::getAllDetailed();

        $grouped = [];

        foreach ($rows as $dish) {
            $categoryName = $dish['category_name'] ?? 'Uncategorized';

            if (!isset($grouped[$categoryName])) {
              $grouped[$categoryName] = [];
        }

        $grouped[$categoryName][] = [
            'id' => $dish['id'],
            'name' => $dish['name'],
            'description' => $dish['description'],
            'price' => $dish['price'],
            'availability' => $dish['availability'],
            'emoji' => '🍽️',
            'cuisine' => $dish['cuisine_name'] ?? '',
            'category' => $categoryName,
        ];
    }

        $data['groupedDishes'] = $grouped;
        $data['dishCount'] = count($rows);
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

        $success = $request->getQueryParams()['success'] ?? null;
        if ($success === 'cuisine') {
            $data['success'] = 'Cuisine added successfully.';
        } elseif ($success === 'category') {
            $data['success'] = 'Category added successfully.';
        } elseif ($success === 'dish') {
            $data['success'] = 'Dish added successfully.';
        }

        return $this->render($response, 'Admin/add.twig', $data);
    }

    public function addCategory(Request $request, Response $response, array $args): Response
{
    $body = $request->getParsedBody();

    $name = trim($body['name'] ?? '');
    $description = trim($body['description'] ?? '');

    $errors = [];

    if ($name === '') {
        $errors[] = 'Category name is required.';
    }

    if ($name !== '' && Categories::findByName($name) !== null) {
        $errors[] = 'A category with that name already exists.';
    }

    if (!empty($errors)) {
        $data['errors'] = $errors;
        $data['cuisines'] = Cuisines::getAll();
        $data['categories'] = Categories::getAll();
        $data['activeNav'] = 'add';
        return $this->render($response, 'Admin/add.twig', $data);
    }

    $createdId = Categories::create($name, $description);

    if ($createdId === 0) {
        $data['errors'] = ['Unable to create category. Please check the values and try again.'];
        $data['cuisines'] = Cuisines::getAll();
        $data['categories'] = Categories::getAll();
        $data['activeNav'] = 'add';
        return $this->render($response, 'Admin/add.twig', $data);
    }

    $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
    return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/add?success=category');
}

    public function addDish(Request $request, Response $response, array $args): Response
{
    $body = $request->getParsedBody();

    $name = trim($body['name'] ?? '');
    $cuisineId = (int) ($body['cuisine_id'] ?? 0);
    $categoryId = (int) ($body['category_id'] ?? 0);
    $description = trim($body['description'] ?? '');
    $price = (float) ($body['price'] ?? 0);
    $availability = trim($body['availability'] ?? 'available');
    $imageUrl = trim($body['image_url'] ?? '');

    $errors = [];

    if ($name === '') {
        $errors[] = 'Dish name is required.';
    }

    if ($cuisineId <= 0) {
        $errors[] = 'Cuisine is required.';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Category is required.';
    }

    if ($price < 0) {
        $errors[] = 'Price cannot be negative.';
    }

    if (!empty($errors)) {
        $data['errors'] = $errors;
        $data['cuisines'] = Cuisines::getAll();
        $data['categories'] = Categories::getAll();
        $data['activeNav'] = 'add';
        return $this->render($response, 'Admin/add.twig', $data);
    }

    $createdId = Dishes::create(
        $categoryId,
        $cuisineId,
        $name,
        $description,
        $price,
        $imageUrl,
        $availability
    );

    if ($createdId === 0) {
        $data['errors'] = ['Unable to create dish. Please check the values and try again.'];
        $data['cuisines'] = Cuisines::getAll();
        $data['categories'] = Categories::getAll();
        $data['activeNav'] = 'add';
        return $this->render($response, 'Admin/add.twig', $data);
    }

    $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
    return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/add?success=dish');
}

public function showEditDish(Request $request, Response $response, array $args): Response
{
    $dish = Dishes::findById((int) $args['id']);

    if ($dish === null) {
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/menu');
    }

    $data['dish'] = $dish;
    $data['cuisines'] = Cuisines::getAll();
    $data['categories'] = Categories::getAll();
    $data['activeNav'] = 'menu';

    $success = $request->getQueryParams()['updated'] ?? null;
    if ($success === '1') {
        $data['success'] = 'Dish updated successfully.';
    }

    return $this->render($response, 'Admin/edit-dish.twig', $data);
}

public function updateDish(Request $request, Response $response, array $args): Response
{
    $body = $request->getParsedBody();
    $dishId = (int) $args['id'];
    $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';

    $name = trim($body['name'] ?? '');
    $cuisineId = (int) ($body['cuisine_id'] ?? 0);
    $categoryId = (int) ($body['category_id'] ?? 0);
    $description = trim($body['description'] ?? '');
    $price = (float) ($body['price'] ?? 0);
    $availability = trim($body['availability'] ?? 'available');
    $imageUrl = trim($body['image_url'] ?? '');

    $errors = [];

    if ($name === '') {
        $errors[] = 'Dish name is required.';
    }

    if ($cuisineId <= 0) {
        $errors[] = 'Cuisine is required.';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Category is required.';
    }

    if ($price < 0) {
        $errors[] = 'Price cannot be negative.';
    }

    if (!empty($errors)) {
        $data['errors'] = $errors;
        $data['dish'] = Dishes::findById($dishId);
        $data['cuisines'] = Cuisines::getAll();
        $data['categories'] = Categories::getAll();
        $data['activeNav'] = 'menu';
        return $this->render($response, 'Admin/edit-dish.twig', $data);
    }

    $updated = Dishes::update(
        $dishId,
        $categoryId,
        $cuisineId,
        $name,
        $description,
        $price,
        $imageUrl,
        $availability
    );

    if (!$updated) {
        $data['errors'] = ['Unable to update dish. Please check the values and try again.'];
        $data['dish'] = Dishes::findById($dishId);
        $data['cuisines'] = Cuisines::getAll();
        $data['categories'] = Categories::getAll();
        $data['activeNav'] = 'menu';
        return $this->render($response, 'Admin/edit-dish.twig', $data);
    }

    return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/edit/dish/' . $dishId . '?updated=1');
}


    public function deleteDish(Request $request, Response $response, array $args): Response
{
    $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
    Dishes::delete((int) $args['id']);
    return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/menu');
}

    public function addCuisine(Request $request, Response $response, array $args): Response
{
    $body = $request->getParsedBody();

    $name = trim($body['name'] ?? '');
    $code = trim($body['code'] ?? '');
    $description = trim($body['description'] ?? '');
    $imageUrl = trim($body['image_url'] ?? '');

    $errors = [];

    if ($name === '') {
        $errors[] = 'Cuisine name is required.';
    }

    if ($code === '') {
        $errors[] = 'Cuisine code is required.';
    }

    if ($name !== '' && Cuisines::findByName($name) !== null) {
        $errors[] = 'A cuisine with that name already exists.';
    }

    if (!empty($errors)) {
        $data['errors'] = $errors;
        $data['cuisines'] = Cuisines::getAll();
        $data['categories'] = Categories::getAll();
        $data['activeNav'] = 'add';
        return $this->render($response, 'Admin/add.twig', $data);
    }

    $createdId = Cuisines::create($name, $code, $description, $imageUrl);

    if ($createdId === 0) {
        $data['errors'] = ['Unable to create cuisine. Please check the values and try again.'];
        $data['cuisines'] = Cuisines::getAll();
        $data['categories'] = Categories::getAll();
        $data['activeNav'] = 'add';
        return $this->render($response, 'Admin/add.twig', $data);
    }

    $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
    return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/add?success=cuisine');
}


    public function deleteCuisine(Request $request, Response $response, array $args): Response
{
    $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
    Cuisines::delete((int) $args['id']);
    return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/add');
}


    public function deleteCategory(Request $request, Response $response, array $args): Response
{
    $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
    Categories::delete((int) $args['id']);
    return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/add');
}

public function updateOrderStatus(Request $request, Response $response, array $args): Response
{
    $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
    $orderId = (int) $args['id'];
    $status = trim($request->getParsedBody()['status'] ?? '');

    $allowedStatuses = ['processing', 'wrapping', 'shipped', 'delivered'];

    if ($orderId > 0 && in_array($status, $allowedStatuses, true)) {
        Orders::updateStatus($orderId, $status);
    }

    return $response->withStatus(302)->withHeader('Location', $basePath . '/admin/orders');
}


}



