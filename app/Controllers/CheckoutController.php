<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
 * CheckoutController — handles the checkout page
 *
 * WHAT: Shows the final order summary, delivery address form, and payment form.
 * HOW:  Reads cart items, calculates subtotal, delivery fee, tax (13%), and total,
 *       then passes them to checkout.twig for display.
 * NOTE: Currently uses dummy data — will be replaced with session cart data later.
 *       The "Place Order" button will eventually save the order to the DB.
 */
class CheckoutController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    // Renders the checkout page with order summary and forms
    public function showCheckout(Request $request, Response $response, array $args): Response
    {
        // --- DUMMY DATA (replace with session/DB later) ---
        $items = [
            ['id' => 1, 'name' => 'Kung Pao Chicken', 'emoji' => '🍛', 'price' => 14.99, 'quantity' => 2],
            ['id' => 2, 'name' => 'Sushi Platter',     'emoji' => '🍱', 'price' => 22.00, 'quantity' => 1],
            ['id' => 3, 'name' => 'Bubble Tea',        'emoji' => '🧋', 'price' => 5.50,  'quantity' => 2],
        ];

        $subtotal     = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
        $delivery_fee = 2.99;
        $tax          = $subtotal * 0.13;
        $total        = $subtotal + $delivery_fee + $tax;
        // --- END DUMMY DATA ---

        $data = [
            'items'        => $items,
            'subtotal'     => number_format($subtotal, 2),
            'delivery_fee' => number_format($delivery_fee, 2),
            'tax'          => number_format($tax, 2),
            'total'        => number_format($total, 2),
        ];

        return $this->render($response, 'checkout.twig', $data);
    }
}
