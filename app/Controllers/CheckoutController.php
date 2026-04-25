<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\DeliveryAddress;
use App\Domain\Models\Dishes;
use App\Domain\Models\Orders;
use App\Domain\Models\OrderDish;
use App\Domain\Models\SavedCart;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CheckoutController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function showCheckout(Request $request, Response $response, array $args): Response
    {
        $cart = $this->getCurrentCart();

        if (empty($cart)) {
            $this->flash('warning', 'Your cart is empty.');
            $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
            return $response->withStatus(302)->withHeader('Location', $basePath . '/cart');
        }

        return $this->render($response, 'checkout.twig', $this->buildSummary($cart));
    }

    public function placeOrder(Request $request, Response $response, array $args): Response
    {
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';

        // Must be logged in
        if (empty($_SESSION['user_id'])) {
            return $response->withStatus(302)->withHeader('Location', $basePath . '/login');
        }

        $cart = $this->getCurrentCart();
        if (empty($cart)) {
            $this->flash('warning', 'Your cart is empty.');
            return $response->withStatus(302)->withHeader('Location', $basePath . '/cart');
        }

        $body = $request->getParsedBody();
        $name       = trim($body['name']        ?? '');
        $street     = trim($body['street']      ?? '');
        $city       = trim($body['city']        ?? '');
        $postalCode = trim($body['postal_code'] ?? '');
        $notes      = trim($body['notes']       ?? '');

        // Validate fields
        $errors = [];
        if ($name === '')       $errors[] = 'Full name is required.';
        if ($street === '')     $errors[] = 'Street address is required.';
        if ($city === '')       $errors[] = 'City is required.';
        if ($postalCode === '') $errors[] = 'Postal code is required.';

        if (!empty($errors)) {
            $summary = $this->buildSummary($cart);
            $summary['errors'] = $errors;
            $summary['old'] = compact('name', 'street', 'city', 'postalCode', 'notes');
            return $this->render($response, 'checkout.twig', $summary);
        }

        $userId = (int) $_SESSION['user_id'];

        // Save delivery address
        $addressId = DeliveryAddress::create($userId, $street, $city, $postalCode);
        if ($addressId === 0) {
            $summary = $this->buildSummary($cart);
            $summary['errors'] = ['Could not save delivery address. Please try again.'];
            return $this->render($response, 'checkout.twig', $summary);
        }

        // Calculate totals
        $dishRows = Dishes::findDetailedByIds(array_keys($cart));
        $dishesById = [];
        foreach ($dishRows as $dish) {
            $dishesById[(int) $dish['id']] = $dish;
        }

        $subtotal = 0.0;
        foreach ($cart as $dishId => $quantity) {
            if (isset($dishesById[$dishId])) {
                $subtotal += (float) $dishesById[$dishId]['price'] * $quantity;
            }
        }

        $deliveryFee = 2.99;
        $taxes       = round($subtotal * 0.13, 2);
        $total       = round($subtotal + $deliveryFee + $taxes, 2);

        // Create order
        $orderId = Orders::create($userId, $addressId, $subtotal, $taxes, $total, 'pending', $notes ?: null);
        if ($orderId === 0) {
            $summary = $this->buildSummary($cart);
            $summary['errors'] = ['Could not place your order. Please try again.'];
            return $this->render($response, 'checkout.twig', $summary);
        }

        // Save order items
        foreach ($cart as $dishId => $quantity) {
            if (isset($dishesById[$dishId])) {
                OrderDish::create($orderId, $dishId, $quantity, (float) $dishesById[$dishId]['price']);
            }
        }

        // Clear cart
        $this->setSessionCart([]);
        SavedCart::clearByUserId($userId);

        $this->flash('success', 'Order placed successfully!');
        return $response->withStatus(302)->withHeader('Location', $basePath . '/account/orders');
    }

    private function buildSummary(array $cart): array
    {
        $dishRows = Dishes::findDetailedByIds(array_keys($cart));
        $dishesById = [];
        foreach ($dishRows as $dish) {
            $dishesById[(int) $dish['id']] = $dish;
        }

        $items = [];
        foreach ($cart as $dishId => $quantity) {
            if (!isset($dishesById[$dishId])) continue;
            $dish = $dishesById[$dishId];
            $items[] = [
                'id'       => $dishId,
                'name'     => $dish['name'],
                'emoji'    => '🍽️',
                'price'    => (float) $dish['price'],
                'quantity' => $quantity,
            ];
        }

        $subtotal    = array_sum(array_map(static fn($i) => $i['price'] * $i['quantity'], $items));
        $deliveryFee = empty($items) ? 0.00 : 2.99;
        $tax         = $subtotal * 0.13;
        $total       = $subtotal + $deliveryFee + $tax;

        return [
            'items'        => $items,
            'subtotal'     => number_format($subtotal, 2),
            'delivery_fee' => number_format($deliveryFee, 2),
            'tax'          => number_format($tax, 2),
            'total'        => number_format($total, 2),
        ];
    }
}
