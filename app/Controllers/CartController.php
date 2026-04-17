<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CartController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        // TODO: load cart items from session/database
        $data = [
            'items'        => [],
            'subtotal'     => '0.00',
            'delivery_fee' => '2.99',
            'total'        => '2.99',
        ];

        return $this->render($response, 'cart.twig', $data);
    }
}
