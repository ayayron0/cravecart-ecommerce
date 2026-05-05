<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Domain\Models\Cuisines;
use App\Domain\Models\Dishes;

/*
 * BrowseController - handles the dish browsing page.
 *
 * WHAT: Shows dishes filtered by cuisine and category.
 * HOW:  The URL /browse/{category}/{slug} provides the category slug
 *       (food, desserts, drinks) and cuisine slug (chinese, japanese, etc.).
 *       The controller loads the cuisine, matching dishes, and the cuisine
 *       switcher list, then passes everything to browse/dishes.twig.
 */
class BrowseController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function showDishes(Request $request, Response $response, array $args): Response
    {
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
            'description' => $cuisineBean->description,
            'image_url' => $cuisineBean->image_url,
        ];

        $dishBeans = Dishes::findByCuisineAndCategory($cuisineSlug, $categorySlug);

        $dishes = array_map(static function ($dish): array {
            return [
                'id' => (int) $dish->id,
                'name' => $dish->name,
                'slug' => $dish->slug,
                'description' => $dish->description,
                'price' => (float) $dish->price,
                'availability' => $dish->availability,
                'image_url' => $dish->image_url ?? null,
            ];
        }, $dishBeans);

        $allCuisineBeans = Cuisines::getAll();
        $cuisines = array_map(static function ($bean): array {
            return [
                'name' => (string) $bean->name,
                'slug' => (string) $bean->slug,
                'code' => (string) $bean->code,
            ];
        }, $allCuisineBeans);

        $data = [
            'cuisine' => $cuisine,
            'category' => $category,
            'cuisines' => $cuisines,
            'dishes' => $dishes,
        ];

        return $this->render($response, 'browse/dishes.twig', $data);
    }
}
