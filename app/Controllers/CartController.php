<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
 * CartController — handles the shopping cart page
 *
 * WHAT: Shows the user's current cart items, quantities, and running total.
 * HOW:  Reads cart items and calculates subtotal, delivery fee, and total,
 *       then passes them to cart.twig.
 * NOTE: Currently uses dummy data — will be replaced with session storage later
 *       so items persist as the user adds them from the browse page.
 */
class CartController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    // Renders the cart page with items and pricing summary
    public function showCart(Request $request, Response $response, array $args): Response
    {
        // --- DUMMY DATA (replace with session/DB later) ---
        $items = [
            ['id' => 1, 'name' => 'Kung Pao Chicken', 'emoji' => '🍛', 'category' => 'Chinese · Food',   'price' => 14.99, 'quantity' => 2],
            ['id' => 2, 'name' => 'Sushi Platter',     'emoji' => '🍱', 'category' => 'Japanese · Food',  'price' => 22.00, 'quantity' => 1],
            ['id' => 3, 'name' => 'Bubble Tea',        'emoji' => '🧋', 'category' => 'Chinese · Drinks', 'price' => 5.50,  'quantity' => 2],
        ];

        // array_map loops over every item and calculates price × quantity for each.
        // array_sum then adds all those subtotals together into one number.
        $subtotal     = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
        $delivery_fee = 2.99;
        $total        = $subtotal + $delivery_fee;
        // --- END DUMMY DATA ---

        $data = [
            'items'        => $items,
            'subtotal'     => number_format($subtotal, 2),
            'delivery_fee' => number_format($delivery_fee, 2),
            'total'        => number_format($total, 2),
        ];

        return $this->render($response, 'cart.twig', $data);
    }
}
