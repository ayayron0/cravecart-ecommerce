<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
 * BrowseController — handles the dish browsing page
 *
 * WHAT: Shows a list of dishes filtered by cuisine and category.
 * HOW:  The URL /browse/{category}/{slug} passes two values — the category
 *       (food, desserts, drinks) and the cuisine slug (chinese, japanese, etc.)
 *       The controller reads both from $args, fetches matching dishes, and
 *       passes them to browse/dishes.twig.
 * NOTE: Currently uses dummy data — will be replaced with DB queries later.
 */
class BrowseController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    // Renders the dish browse page for a given cuisine and category
    public function showDishes(Request $request, Response $response, array $args): Response
    {
        // Read the two URL segments captured by the route {category} and {slug}
        $cuisineSlug  = $args['slug'];
        $categorySlug = $args['category'];

        // --- DUMMY DATA (replace with DB later) ---
        $cuisine = [
            'name'        => ucfirst($cuisineSlug),
            'slug'        => $cuisineSlug,
            'flag'        => '🍜',
            'description' => 'A selection of popular ' . ucfirst($cuisineSlug) . ' dishes.',
        ];

        $category = [
            'name' => ucfirst($categorySlug),
            'slug' => $categorySlug,
        ];

        // Dummy dishes labelled with the category so you can see filtering works
        $dishes = [
            [
                'name'         => ucfirst($cuisineSlug) . ' ' . ucfirst($categorySlug) . ' Dish 1',
                'description'  => 'A delicious ' . $categorySlug . ' dish with rich flavours.',
                'price'        => '12.99',
                'emoji'        => '🍛',
                'availability' => 'available',
            ],
            [
                'name'         => ucfirst($cuisineSlug) . ' ' . ucfirst($categorySlug) . ' Dish 2',
                'description'  => 'Light and fresh, perfect for any time.',
                'price'        => '9.50',
                'emoji'        => '🥗',
                'availability' => 'available',
            ],
            [
                'name'         => ucfirst($cuisineSlug) . ' ' . ucfirst($categorySlug) . ' Dish 3',
                'description'  => 'A classic favourite, slow-cooked to perfection.',
                'price'        => '15.00',
                'emoji'        => '🍲',
                'availability' => 'seasonal',
            ],
        ];
        // --- END DUMMY DATA ---

        $data = [
            'cuisine'  => $cuisine,
            'category' => $category,
            'dishes'   => $dishes,
        ];

        return $this->render($response, 'browse/dishes.twig', $data);
    }
}
