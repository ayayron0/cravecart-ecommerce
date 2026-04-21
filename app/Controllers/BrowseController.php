<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Domain\Models\Cuisines;
use App\Domain\Models\Dishes;


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
         $cuisineSlug = $args['slug'];
        $categorySlug = $args['category'];

        $cuisineBean = Cuisines::findBySlug($cuisineSlug);

        if ($cuisineBean === null) {
            return $this->render($response->withStatus(404), 'errors/404.twig');
        }

        $category = [
            'name' => ucfirst($categorySlug),
            'slug' => $categorySlug,
        ];

        $cuisine = [
            'name' => $cuisineBean->name,
            'slug' => $cuisineBean->slug,
            'flag' => '🍜',
            'description' => $cuisineBean->description,
        ];

        $dishBeans = Dishes::findByCuisineAndCategory($cuisineSlug, $categorySlug);

        $dishes = array_map(static function ($dish): array {
            return [
                'name' => $dish->name,
                'description' => $dish->description,
                'price' => (float) $dish->price,
                'emoji' => '🍽️',
                'availability' => $dish->availability,
            ];
        }, $dishBeans);

        $data = [
            'cuisine' => $cuisine,
            'category' => $category,
            'dishes' => $dishes,
        ];

        return $this->render($response, 'browse/dishes.twig', $data);
    }
}
