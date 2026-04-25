<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\Dishes;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
 * CheckoutController — handles the checkout page
 *
 * WHAT: Shows the final order summary, delivery address form, and payment form.
 * HOW:  Reads the current session cart, calculates subtotal, delivery fee,
 *       tax (13%), and total, then passes them to checkout.twig for display.
 * NOTE: The summary is now real, but the "Place Order" button is still only
 *       a placeholder until the actual checkout save flow is built.
 */
class CheckoutController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    // Renders the checkout page with a real order summary from the current cart.
    public function showCheckout(Request $request, Response $response, array $args): Response
    {
        $cart = $this->getCurrentCart();

        if (empty($cart)) {
            $this->flash('warning', 'Your cart is empty.');
            $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
            return $response->withStatus(302)->withHeader('Location', $basePath . '/cart');
        }

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
                'emoji' => '🍽️',
                'price' => (float) $dish['price'],
                'quantity' => $quantity,
            ];
        }

        $subtotal = array_sum(array_map(static fn(array $item): float => $item['price'] * $item['quantity'], $items));
        $deliveryFee = empty($items) ? 0.00 : 2.99;
        $tax = $subtotal * 0.13;
        $total = $subtotal + $deliveryFee + $tax;

        return $this->render($response, 'checkout.twig', [
            'items' => $items,
            'subtotal' => number_format($subtotal, 2),
            'delivery_fee' => number_format($deliveryFee, 2),
            'tax' => number_format($tax, 2),
            'total' => number_format($total, 2),
        ]);
    }
}
