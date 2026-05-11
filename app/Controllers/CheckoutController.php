<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\DeliveryAddress;
use App\Domain\Models\Dishes;
use App\Domain\Models\OrderDish;
use App\Domain\Models\Orders;
use App\Domain\Models\SavedCart;
use App\Services\ExchangeRateService;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
 * CheckoutController - handles the checkout page and order placement.
 *
 * WHAT: Shows the checkout summary, validates the delivery form, and creates
 *       the final order records.
 * HOW:  Reads the current cart, calculates totals, saves the delivery address,
 *       creates the order and order items, then clears the cart on success.
 */
class CheckoutController extends BaseController
{
    private ExchangeRateService $exchangeRateService;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->exchangeRateService = new ExchangeRateService();
    }

    public function showCheckout(Request $request, Response $response, array $args): Response
    {
        $cart = $this->getCurrentCart();

        if (empty($cart)) {
            $this->flash('warning', __('checkout.cart_empty'));
            $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
            return $response->withStatus(302)->withHeader('Location', $basePath . '/cart');
        }

        return $this->render($response, 'checkout.twig', $this->buildSummary($cart));
    }

    public function placeOrder(Request $request, Response $response, array $args): Response
    {
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';

        if (empty($_SESSION['user_id'])) {
            return $response->withStatus(302)->withHeader('Location', $basePath . '/login');
        }

        $cart = $this->getCurrentCart();
        if (empty($cart)) {
            $this->flash('warning', __('checkout.cart_empty'));
            return $response->withStatus(302)->withHeader('Location', $basePath . '/cart');
        }

        $body = $request->getParsedBody();
        $name       = trim($body['name'] ?? '');
        $street     = trim($body['street'] ?? '');
        $city       = trim($body['city'] ?? '');
        $postalCode = trim($body['postal_code'] ?? '');
        $notes      = trim($body['notes'] ?? '');

        $errors = [];
        if ($name === '') {
            $errors[] = __('checkout.full_name') . ' ' . __('forms.required');
        }
        if ($street === '') {
            $errors[] = __('checkout.street_address') . ' ' . __('forms.required');
        }
        if ($city === '') {
            $errors[] = __('checkout.city') . ' ' . __('forms.required');
        }
        if ($postalCode === '') {
            $errors[] = __('checkout.postal_code') . ' ' . __('forms.required');
        }

        if (!empty($errors)) {
            $summary = $this->buildSummary($cart);
            $summary['errors'] = $errors;
            $summary['old'] = compact('name', 'street', 'city', 'postalCode', 'notes');
            return $this->render($response, 'checkout.twig', $summary);
        }

        $userId = (int) $_SESSION['user_id'];

        $addressId = DeliveryAddress::create($userId, $street, $city, $postalCode);
        if ($addressId === 0) {
            $summary = $this->buildSummary($cart);
            $summary['errors'] = [__('errors.something_went_wrong')];
            return $this->render($response, 'checkout.twig', $summary);
        }

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
        $taxes = round($subtotal * 0.13, 2);
        $total = round($subtotal + $deliveryFee + $taxes, 2);

        $orderId = Orders::create($userId, $addressId, $subtotal, $taxes, $total, 'pending', $notes ?: null);
        if ($orderId === 0) {
            $summary = $this->buildSummary($cart);
            $summary['errors'] = [__('checkout.order_failed')];
            return $this->render($response, 'checkout.twig', $summary);
        }

        foreach ($cart as $dishId => $quantity) {
            if (isset($dishesById[$dishId])) {
                OrderDish::create($orderId, $dishId, $quantity, (float) $dishesById[$dishId]['price']);
            }
        }

        $this->setSessionCart([]);
        SavedCart::clearByUserId($userId);

        $this->flash('success', __('checkout.order_success'));
        return $response->withStatus(302)->withHeader('Location', $basePath . '/account/orders');
    }

    // Builds the exact summary data the checkout view needs.
    private function buildSummary(array $cart): array
    {
        $dishRows = Dishes::findDetailedByIds(array_keys($cart));
        $dishesById = [];
        foreach ($dishRows as $dish) {
            $dishesById[(int) $dish['id']] = $dish;
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
                'price' => (float) $dish['price'],
                'quantity' => $quantity,
            ];
        }

        $subtotal = array_sum(array_map(static fn(array $item): float => $item['price'] * $item['quantity'], $items));
        $deliveryFee = empty($items) ? 0.00 : 2.99;
        $tax = $subtotal * 0.13;
        $total = $subtotal + $deliveryFee + $tax;
        $usdTotal = $this->exchangeRateService->convertCadToUsd($total);

        return [
            'items' => $items,
            'subtotal' => number_format($subtotal, 2),
            'delivery_fee' => number_format($deliveryFee, 2),
            'tax' => number_format($tax, 2),
            'total' => number_format($total, 2),
            'usd_total' => $usdTotal !== null ? number_format($usdTotal, 2) : null,
        ];
    }
}
