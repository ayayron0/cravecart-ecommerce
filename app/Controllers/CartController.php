<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\Dishes;
use App\Domain\Models\SavedCart;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
 * CartController — handles the shopping cart page
 *
 * WHAT: Shows the current cart, lets users add dishes, update quantities,
 *       and remove items.
 * HOW:  Uses the session cart for the current browser state, and mirrors the
 *       cart to saved_cart for logged-in clients so it persists across logins.
 */
class CartController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    // Renders the cart page using the current session cart.
    public function showCart(Request $request, Response $response, array $args): Response
    {
        $cart = $this->getCurrentCart();
        $dishIds = array_keys($cart);
        $dishRows = Dishes::findDetailedByIds($dishIds);

        $dishesById = [];
        foreach ($dishRows as $row) {
            $dishesById[(int) $row['id']] = $row;
        }

        $items = [];
        foreach ($cart as $dishId => $quantity) {
            if (!isset($dishesById[$dishId])) {
                continue;
            }

            $dish = $dishesById[$dishId];
            $items[] = [
                'id' => $dishId,
                'name' => $dish['name'],
                'emoji' => '🍽️',
                'category' => ($dish['cuisine_name'] ?? '') . ' · ' . ($dish['category_name'] ?? ''),
                'price' => (float) $dish['price'],
                'quantity' => $quantity,
                'image_url' => $dish['image_url'] ?? null,
            ];
        }

        // If dishes were deleted from the DB after being added to the cart,
        // rebuild the session cart from the valid items we still have.
        if (count($items) !== count($cart)) {
            $cleanCart = [];
            foreach ($items as $item) {
                $cleanCart[$item['id']] = $item['quantity'];
            }

            $this->setSessionCart($cleanCart);

            $userId = $this->getClientUserId();
            if ($userId !== null) {
                SavedCart::clearByUserId($userId);
                foreach ($cleanCart as $dishId => $quantity) {
                    $dish = Dishes::findById($dishId);
                    if ($dish !== null) {
                        SavedCart::addItem($userId, $dishId, $quantity, (float) $dish->price);
                    }
                }
                $this->refreshSessionCartFromSavedCart($userId);
            }
        }

        // array_map loops over every item and calculates price × quantity for each.
        // array_sum then adds all those subtotals together into one number.
        $subtotal = array_sum(array_map(static fn(array $item): float => $item['price'] * $item['quantity'], $items));
        $deliveryFee = empty($items) ? 0.00 : 2.99;
        $total = $subtotal + $deliveryFee;

        return $this->render($response, 'cart.twig', [
            'items' => $items,
            'subtotal' => number_format($subtotal, 2),
            'delivery_fee' => number_format($deliveryFee, 2),
            'total' => number_format($total, 2),
        ]);
    }

    // Adds a dish to the cart, then mirrors it to saved_cart for clients.
    public function addItem(Request $request, Response $response, array $args): Response
    {
        $dishId = (int) $args['id'];
        $dish = Dishes::findById($dishId);

        if ($dish === null) {
            $this->flash('danger', __('cart.dish_not_found'));
            return $this->redirectBack($request, $response, '/');
        }

        if (!in_array((string) $dish->availability, ['available', 'seasonal'], true)) {
            $this->flash('warning', __('cart.dish_unavailable'));
            return $this->redirectBack($request, $response, '/');
        }

        $cart = $this->getCurrentCart();
        $cart[$dishId] = ($cart[$dishId] ?? 0) + 1;
        $this->setSessionCart($cart);

        $this->persistCurrentCartForClient();

        $this->flash('success', $dish->name . ' ' . __('cart.item_added'));
        return $this->redirectBack($request, $response, '/cart');
    }

    // Increases the quantity of a dish already in the cart.
    public function increaseQuantity(Request $request, Response $response, array $args): Response
    {
        $dishId = (int) $args['id'];
        $cart = $this->getCurrentCart();

        if (!isset($cart[$dishId])) {
            $this->flash('danger', __('cart.cart_item_not_found'));
            return $this->redirectTo($response, '/cart');
        }

        $cart[$dishId]++;
        $this->setSessionCart($cart);
        $this->persistCurrentCartForClient();

        return $this->redirectTo($response, '/cart');
    }

    // Decreases the quantity of a dish, removing it if the quantity reaches 0.
    public function decreaseQuantity(Request $request, Response $response, array $args): Response
    {
        $dishId = (int) $args['id'];
        $cart = $this->getCurrentCart();

        if (!isset($cart[$dishId])) {
            $this->flash('danger', __('cart.cart_item_not_found'));
            return $this->redirectTo($response, '/cart');
        }

        $cart[$dishId]--;

        if ($cart[$dishId] <= 0) {
            unset($cart[$dishId]);
        }

        $this->setSessionCart($cart);
        $this->persistCurrentCartForClient();

        return $this->redirectTo($response, '/cart');
    }

    // Removes a dish from the cart completely.
    public function removeItem(Request $request, Response $response, array $args): Response
    {
        $dishId = (int) $args['id'];
        $cart = $this->getCurrentCart();

        if (!isset($cart[$dishId])) {
            $this->flash('danger', __('cart.cart_item_not_found'));
            return $this->redirectTo($response, '/cart');
        }

        unset($cart[$dishId]);
        $this->setSessionCart($cart);
        $this->persistCurrentCartForClient();

        $this->flash('success', __('cart.item_removed'));
        return $this->redirectTo($response, '/cart');
    }

    // Returns the logged-in client user ID, or null for guests/admins.
    private function getClientUserId(): ?int
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? '';

        if ($userId <= 0 || $role === 'administrator') {
            return null;
        }

        return $userId;
    }

    // Keeps saved_cart synchronized with the current session cart for clients.
    private function persistCurrentCartForClient(): void
    {
        $userId = $this->getClientUserId();
        if ($userId === null) {
            return;
        }

        $cart = $this->normaliseCart($_SESSION['cart'] ?? []);
        SavedCart::clearByUserId($userId);

        foreach ($cart as $dishId => $quantity) {
            $dish = Dishes::findById($dishId);
            if ($dish === null) {
                continue;
            }

            SavedCart::addItem($userId, $dishId, $quantity, (float) $dish->price);
        }

        $this->refreshSessionCartFromSavedCart($userId);
    }

    // Redirect helper for controllers that build local absolute paths.
    private function redirectTo(Response $response, string $path): Response
    {
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        return $response->withStatus(302)->withHeader('Location', $basePath . $path);
    }

    // Redirects to the previous page when possible, otherwise falls back locally.
    private function redirectBack(Request $request, Response $response, string $fallbackPath): Response
    {
        $referer = trim($request->getHeaderLine('Referer'));
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';

        if ($referer !== '') {
            $appBaseUrl = rtrim((string) $request->getUri()->getScheme() . '://' . $request->getUri()->getAuthority() . $basePath, '/');
            if (str_starts_with($referer, $appBaseUrl)) {
                return $response->withStatus(302)->withHeader('Location', $referer);
            }
        }

        return $this->redirectTo($response, $fallbackPath);
    }
}
