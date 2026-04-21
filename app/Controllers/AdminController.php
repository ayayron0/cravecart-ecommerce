<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\Categories;
use App\Domain\Models\Cuisines;
use App\Domain\Models\Dishes;
use App\Domain\Models\OrderDish;
use App\Domain\Models\Orders;
use App\Domain\Models\Users;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
 * AdminController - handles all admin panel pages
 *
 * WHAT: Controls the pages only administrators can access.
 * HOW:  Every route in the /admin group is protected by AdminMiddleware,
 *       which checks the session before this controller ever runs.
 *       Each method fetches the data needed for its page and renders the view.
 * NOTE: This controller now uses the database-backed model layer.
 */
class AdminController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    // Renders the orders dashboard - shows all customer orders and their status.
    // Converts DB rows into the exact data shape Admin/orders.twig expects.
    public function orders(Request $request, Response $response, array $args): Response
    {
        $orderRows = Orders::findAllDetailed();

        $orders = array_map(function (array $order): array {
            $items = OrderDish::findDetailedByOrderId((int) $order['id']);

            $itemNames = array_map(
                static fn(array $item): string => $item['dish_name'],
                $items
            );

            // Map DB statuses to the UI labels used by the admin dashboard.
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

        return $this->render($response, 'Admin/orders.twig', [
            'orders' => $orders,
            'activeNav' => 'orders',
        ]);
    }

    // Renders the menu management page - admin can view, edit, and delete dishes.
    // Groups dishes by category so the menu page is easier to scan.
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

        return $this->render($response, 'Admin/menu.twig', [
            'groupedDishes' => $grouped,
            'dishCount' => count($rows),
            'activeNav' => 'menu',
        ]);
    }

    // Renders the profile page pre-filled with the admin's current info.
    public function showProfile(Request $request, Response $response, array $args): Response
    {
        return $this->render($response, 'Admin/profile.twig', [
            'user' => Users::findById((int) $_SESSION['user_id']),
            'activeNav' => 'profile',
            'updated' => (($request->getQueryParams()['updated'] ?? null) === '1'),
        ]);
    }

    // Handles the profile update form submission (email and/or password).
    public function updateProfile(Request $request, Response $response, array $args): Response
    {
        // Read submitted form data. trim() removes accidental spaces.
        $body = $request->getParsedBody();
        $errors = [];

        // --- Update name and email ---
        $name = trim($body['name'] ?? '');
        $email = trim($body['email'] ?? '');

        if ($name === '' || $email === '') {
            $errors[] = 'Name and email cannot be empty.';
        } else {
            Users::update((int) $_SESSION['user_id'], $name, $email);

            // Keep the session in sync so the navbar shows the updated name immediately.
            $_SESSION['name'] = $name;
        }

        // --- Update password (only runs if the user filled in at least one password field) ---
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

        // If there are any errors, re-render the form with the error messages.
        if (!empty($errors)) {
            return $this->render($response, 'Admin/profile.twig', [
                'user' => Users::findById((int) $_SESSION['user_id']),
                'errors' => $errors,
                'activeNav' => 'profile',
            ]);
        }

        // All updates succeeded - redirect back to profile with ?updated=1.
        return $this->redirectTo($response, '/admin/profile?updated=1');
    }

    // Renders the "Add New" page with cuisines and categories loaded from the DB.
    public function showAdd(Request $request, Response $response, array $args): Response
    {
        $success = $request->getQueryParams()['success'] ?? null;
        $successMessage = null;

        if ($success === 'cuisine') {
            $successMessage = 'Cuisine added successfully.';
        } elseif ($success === 'category') {
            $successMessage = 'Category added successfully.';
        } elseif ($success === 'dish') {
            $successMessage = 'Dish added successfully.';
        }

        return $this->renderAddPage($response, [
            'success' => $successMessage,
        ]);
    }

    // Handles the add-category form submission.
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
            return $this->renderAddPage($response, ['errors' => $errors]);
        }

        $createdId = Categories::create($name, $description);

        if ($createdId === 0) {
            return $this->renderAddPage($response, [
                'errors' => ['Unable to create category. Please check the values and try again.'],
            ]);
        }

        return $this->redirectTo($response, '/admin/add?success=category');
    }

        // Renders the edit form for a single category.
    public function showEditCategory(Request $request, Response $response, array $args): Response
    {
        $category = Categories::findById((int) $args['id']);

        if ($category === null) {
            return $this->redirectTo($response, '/admin/add');
        }

        return $this->render($response, 'Admin/edit-category.twig', [
            'category' => $category,
            'activeNav' => 'add',
            'success' => (($request->getQueryParams()['updated'] ?? null) === '1')
                ? 'Category updated successfully.'
                : null,
        ]);
    }

    // Handles the edit-category form submission.
    public function updateCategory(Request $request, Response $response, array $args): Response
    {
        $categoryId = (int) $args['id'];
        $body = $request->getParsedBody();

        $name = trim($body['name'] ?? '');
        $description = trim($body['description'] ?? '');

        $errors = [];

        if ($name === '') {
            $errors[] = 'Category name is required.';
        }

        $existing = Categories::findByName($name);
        if ($name !== '' && $existing !== null && (int) $existing->id !== $categoryId) {
            $errors[] = 'A category with that name already exists.';
        }

        if (!empty($errors)) {
            return $this->render($response, 'Admin/edit-category.twig', [
                'category' => Categories::findById($categoryId),
                'errors' => $errors,
                'activeNav' => 'add',
            ]);
        }

        $updated = Categories::update($categoryId, $name, $description);

        if (!$updated) {
            return $this->render($response, 'Admin/edit-category.twig', [
                'category' => Categories::findById($categoryId),
                'errors' => ['Unable to update category. Please check the values and try again.'],
                'activeNav' => 'add',
            ]);
        }

        return $this->redirectTo($response, '/admin/edit/category/' . $categoryId . '?updated=1');
    }

    // Handles the add-cuisine form submission.
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
            return $this->renderAddPage($response, ['errors' => $errors]);
        }

        $createdId = Cuisines::create($name, $code, $description, $imageUrl);

        if ($createdId === 0) {
            return $this->renderAddPage($response, [
                'errors' => ['Unable to create cuisine. Please check the values and try again.'],
            ]);
        }

        return $this->redirectTo($response, '/admin/add?success=cuisine');
    }

        // Renders the edit form for a single cuisine.
    public function showEditCuisine(Request $request, Response $response, array $args): Response
    {
        $cuisine = Cuisines::findById((int) $args['id']);

        if ($cuisine === null) {
            return $this->redirectTo($response, '/admin/add');
        }

        return $this->render($response, 'Admin/edit-cuisine.twig', [
            'cuisine' => $cuisine,
            'activeNav' => 'add',
            'success' => (($request->getQueryParams()['updated'] ?? null) === '1')
                ? 'Cuisine updated successfully.'
                : null,
        ]);
    }

    // Handles the edit-cuisine form submission.
    public function updateCuisine(Request $request, Response $response, array $args): Response
    {
        $cuisineId = (int) $args['id'];
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

        $existing = Cuisines::findByName($name);
        if ($name !== '' && $existing !== null && (int) $existing->id !== $cuisineId) {
            $errors[] = 'A cuisine with that name already exists.';
        }

        if (!empty($errors)) {
            return $this->render($response, 'Admin/edit-cuisine.twig', [
                'cuisine' => Cuisines::findById($cuisineId),
                'errors' => $errors,
                'activeNav' => 'add',
            ]);
        }

        $updated = Cuisines::update($cuisineId, $name, $code, $description, $imageUrl);

        if (!$updated) {
            return $this->render($response, 'Admin/edit-cuisine.twig', [
                'cuisine' => Cuisines::findById($cuisineId),
                'errors' => ['Unable to update cuisine. Please check the values and try again.'],
                'activeNav' => 'add',
            ]);
        }

        return $this->redirectTo($response, '/admin/edit/cuisine/' . $cuisineId . '?updated=1');
    }

    // Handles the add-dish form submission.
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
            return $this->renderAddPage($response, ['errors' => $errors]);
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
            return $this->renderAddPage($response, [
                'errors' => ['Unable to create dish. Please check the values and try again.'],
            ]);
        }

        return $this->redirectTo($response, '/admin/add?success=dish');
    }

    // Renders the edit form for a single dish.
    public function showEditDish(Request $request, Response $response, array $args): Response
    {
        $dish = Dishes::findById((int) $args['id']);

        if ($dish === null) {
            return $this->redirectTo($response, '/admin/menu');
        }

        return $this->render($response, 'Admin/edit-dish.twig', [
            'dish' => $dish,
            'cuisines' => Cuisines::getAll(),
            'categories' => Categories::getAll(),
            'activeNav' => 'menu',
            'success' => (($request->getQueryParams()['updated'] ?? null) === '1')
                ? 'Dish updated successfully.'
                : null,
        ]);
    }

    // Handles the edit-dish form submission.
    public function updateDish(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody();
        $dishId = (int) $args['id'];

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
            return $this->render($response, 'Admin/edit-dish.twig', [
                'errors' => $errors,
                'dish' => Dishes::findById($dishId),
                'cuisines' => Cuisines::getAll(),
                'categories' => Categories::getAll(),
                'activeNav' => 'menu',
            ]);
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
            return $this->render($response, 'Admin/edit-dish.twig', [
                'errors' => ['Unable to update dish. Please check the values and try again.'],
                'dish' => Dishes::findById($dishId),
                'cuisines' => Cuisines::getAll(),
                'categories' => Categories::getAll(),
                'activeNav' => 'menu',
            ]);
        }

        return $this->redirectTo($response, '/admin/edit/dish/' . $dishId . '?updated=1');
    }

    // Deletes a dish and returns to the menu page.
    public function deleteDish(Request $request, Response $response, array $args): Response
    {
        Dishes::delete((int) $args['id']);
        return $this->redirectTo($response, '/admin/menu');
    }

    // Deletes a cuisine and returns to the add page.
    public function deleteCuisine(Request $request, Response $response, array $args): Response
    {
        Cuisines::delete((int) $args['id']);
        return $this->redirectTo($response, '/admin/add');
    }

    // Deletes a category and returns to the add page.
    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        Categories::delete((int) $args['id']);
        return $this->redirectTo($response, '/admin/add');
    }

    // Updates an order's status from the admin orders page.
    public function updateOrderStatus(Request $request, Response $response, array $args): Response
    {
        $orderId = (int) $args['id'];
        $status = trim($request->getParsedBody()['status'] ?? '');

        $allowedStatuses = ['processing', 'wrapping', 'shipped', 'delivered'];

        if ($orderId > 0 && in_array($status, $allowedStatuses, true)) {
            Orders::updateStatus($orderId, $status);
        }

        return $this->redirectTo($response, '/admin/orders');
    }

    // Renders the Add New page with the shared data it always needs.
    private function renderAddPage(Response $response, array $extraData = []): Response
    {
        $data = array_merge([
            'cuisines' => Cuisines::getAll(),
            'categories' => Categories::getAll(),
            'activeNav' => 'add',
        ], $extraData);

        return $this->render($response, 'Admin/add.twig', $data);
    }

    // Small redirect helper so we do not repeat base path logic everywhere.
    private function redirectTo(Response $response, string $path): Response
    {
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . $path);
    }
}
