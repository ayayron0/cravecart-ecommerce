<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\Categories;
use App\Domain\Models\Cuisines;
use App\Domain\Models\Dishes;
use App\Domain\Models\OrderDish;
use App\Domain\Models\Orders;
use App\Domain\Models\Users;
use App\Services\Validation\CategoryValidator;
use App\Services\Validation\CuisineValidator;
use App\Services\Validation\DishValidator;
use App\Services\Validation\ProfileValidator;
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
    private CategoryValidator $categoryValidator;
    private CuisineValidator $cuisineValidator;
    private DishValidator $dishValidator;
    private ProfileValidator $profileValidator;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->categoryValidator = new CategoryValidator();
        $this->cuisineValidator = new CuisineValidator();
        $this->dishValidator = new DishValidator();
        $this->profileValidator = new ProfileValidator();
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
        ]);
    }

    // Handles the profile update form submission (email and/or password).
    public function updateProfile(Request $request, Response $response, array $args): Response
    {
        // Read submitted form data, then let the validation service centralize the rules.
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

            // Keep the session in sync so the navbar shows the updated name immediately.
            $_SESSION['name'] = $data['name'];

            // Change the password only after the current password has been verified.
            if ($isChangingPassword) {
                Users::updatePassword((int) $_SESSION['user_id'], $data['current_password'], $data['new_password']);
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

        // All updates succeeded - redirect back to profile with a flash message.
        $this->flash('success', __('account.profile_updated'));
        return $this->redirectTo($response, '/admin/profile');
    }

    // Renders the "Add New" page with cuisines and categories loaded from the DB.
    public function showAdd(Request $request, Response $response, array $args): Response
    {
        return $this->renderAddPage($response);
    }

    // Handles the add-category form submission.
    public function addCategory(Request $request, Response $response, array $args): Response
    {
        $result = $this->categoryValidator->validate($request->getParsedBody());

        if (!$result['valid']) {
            return $this->renderAddPage($response, ['errors' => $result['errors']]);
        }

        $data = $result['data'];
        $createdId = Categories::create($data['name'], $data['description']);

        if ($createdId === 0) {
            return $this->renderAddPage($response, [
                'errors' => ['Unable to create category. Please check the values and try again.'],
            ]);
        }

        $this->flash('success', __('admin.category_added'));
        return $this->redirectTo($response, '/admin/add');
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
        ]);
    }

    // Handles the edit-category form submission.
    public function updateCategory(Request $request, Response $response, array $args): Response
    {
        $categoryId = (int) $args['id'];
        $result = $this->categoryValidator->validate($request->getParsedBody(), $categoryId);

        if (!$result['valid']) {
            return $this->render($response, 'Admin/edit-category.twig', [
                'category' => Categories::findById($categoryId),
                'errors' => $result['errors'],
                'activeNav' => 'add',
            ]);
        }

        $data = $result['data'];
        $updated = Categories::update($categoryId, $data['name'], $data['description']);

        if (!$updated) {
            return $this->render($response, 'Admin/edit-category.twig', [
                'category' => Categories::findById($categoryId),
                'errors' => ['Unable to update category. Please check the values and try again.'],
                'activeNav' => 'add',
            ]);
        }

        $this->flash('success', __('admin.category_updated'));
        return $this->redirectTo($response, '/admin/edit/category/' . $categoryId);
    }

    // Handles the add-cuisine form submission.
    public function addCuisine(Request $request, Response $response, array $args): Response
    {
        $result = $this->cuisineValidator->validate($request->getParsedBody());

        if (!$result['valid']) {
            return $this->renderAddPage($response, ['errors' => $result['errors']]);
        }

        $data = $result['data'];
        $createdId = Cuisines::create($data['name'], $data['code'], $data['description'], $data['image_url']);

        if ($createdId === 0) {
            return $this->renderAddPage($response, [
                'errors' => ['Unable to create cuisine. Please check the values and try again.'],
            ]);
        }

        $this->flash('success', __('admin.cuisine_added'));
        return $this->redirectTo($response, '/admin/add');
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
        ]);
    }

    // Handles the edit-cuisine form submission.
    public function updateCuisine(Request $request, Response $response, array $args): Response
    {
        $cuisineId = (int) $args['id'];
        $result = $this->cuisineValidator->validate($request->getParsedBody(), $cuisineId);

        if (!$result['valid']) {
            return $this->render($response, 'Admin/edit-cuisine.twig', [
                'cuisine' => Cuisines::findById($cuisineId),
                'errors' => $result['errors'],
                'activeNav' => 'add',
            ]);
        }

        $data = $result['data'];
        $updated = Cuisines::update($cuisineId, $data['name'], $data['code'], $data['description'], $data['image_url']);

        if (!$updated) {
            return $this->render($response, 'Admin/edit-cuisine.twig', [
                'cuisine' => Cuisines::findById($cuisineId),
                'errors' => ['Unable to update cuisine. Please check the values and try again.'],
                'activeNav' => 'add',
            ]);
        }

        $this->flash('success', __('admin.cuisine_updated'));
        return $this->redirectTo($response, '/admin/edit/cuisine/' . $cuisineId);
    }

    // Handles the add-dish form submission.
    public function addDish(Request $request, Response $response, array $args): Response
    {
        $result = $this->dishValidator->validate($request->getParsedBody());

        if (!$result['valid']) {
            return $this->renderAddPage($response, ['errors' => $result['errors']]);
        }

        $data = $result['data'];
        $createdId = Dishes::create(
            $data['category_id'],
            $data['cuisine_id'],
            $data['name'],
            $data['description'],
            $data['price'],
            $data['image_url'],
            $data['availability']
        );

        if ($createdId === 0) {
            return $this->renderAddPage($response, [
                'errors' => ['Unable to create dish. Please check the values and try again.'],
            ]);
        }

        $this->flash('success', __('admin.dish_added'));
        return $this->redirectTo($response, '/admin/add');
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
        ]);
    }

    // Handles the edit-dish form submission.
    public function updateDish(Request $request, Response $response, array $args): Response
    {
        $dishId = (int) $args['id'];
        $result = $this->dishValidator->validate($request->getParsedBody());

        if (!$result['valid']) {
            return $this->render($response, 'Admin/edit-dish.twig', [
                'errors' => $result['errors'],
                'dish' => Dishes::findById($dishId),
                'cuisines' => Cuisines::getAll(),
                'categories' => Categories::getAll(),
                'activeNav' => 'menu',
            ]);
        }

        $data = $result['data'];
        $updated = Dishes::update(
            $dishId,
            $data['category_id'],
            $data['cuisine_id'],
            $data['name'],
            $data['description'],
            $data['price'],
            $data['image_url'],
            $data['availability']
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

        $this->flash('success', __('admin.dish_updated'));
        return $this->redirectTo($response, '/admin/edit/dish/' . $dishId);
    }

    // Deletes a dish and returns to the menu page.
    public function deleteDish(Request $request, Response $response, array $args): Response
    {
        Dishes::delete((int) $args['id']);
        $this->flash('success', __('admin.dish_deleted'));
        return $this->redirectTo($response, '/admin/menu');
    }

    // Deletes a cuisine and returns to the add page.
    public function deleteCuisine(Request $request, Response $response, array $args): Response
    {
        Cuisines::delete((int) $args['id']);
        $this->flash('success', __('admin.cuisine_deleted'));
        return $this->redirectTo($response, '/admin/add');
    }

    // Deletes a category and returns to the add page.
    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        Categories::delete((int) $args['id']);
        $this->flash('success', __('admin.category_deleted'));
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
            $this->flash('success', __('admin.order_status_updated'));
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
