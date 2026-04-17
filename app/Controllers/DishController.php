<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

class DishController extends BaseController
{
    private array $categories = [
        'food'     => ['id' => 1, 'name' => 'Food',     'slug' => 'food',     'emoji' => '🍛', 'description' => 'Main dishes from various cuisines'],
        'desserts' => ['id' => 2, 'name' => 'Desserts', 'slug' => 'desserts', 'emoji' => '🍰', 'description' => 'Sweet treats and cakes'],
        'drinks'   => ['id' => 3, 'name' => 'Drinks',   'slug' => 'drinks',   'emoji' => '🥤', 'description' => 'Cold and hot beverages'],
    ];

    private array $cuisines = [
        'pakistani' => ['id' => 1, 'name' => 'Pakistani', 'slug' => 'pakistani', 'flag' => '🇵🇰', 'hero_emoji' => '🍛', 'description' => 'Rich and aromatic South Asian cuisine'],
        'japanese'  => ['id' => 2, 'name' => 'Japanese',  'slug' => 'japanese',  'flag' => '🇯🇵', 'hero_emoji' => '🍱', 'description' => 'Fresh and delicate East Asian cuisine'],
        'italian'   => ['id' => 3, 'name' => 'Italian',   'slug' => 'italian',   'flag' => '🇮🇹', 'hero_emoji' => '🍝', 'description' => 'Classic Mediterranean comfort food'],
        'french'    => ['id' => 4, 'name' => 'French',    'slug' => 'french',    'flag' => '🇫🇷', 'hero_emoji' => '🥐', 'description' => 'Refined and elegant European cuisine'],
    ];

    // Seed data keyed by [category_slug][cuisine_slug]
    private array $dishes = [
        'food' => [
            'pakistani' => [
                ['name' => 'Chicken Biryani',  'description' => 'Aromatic basmati rice layered with spiced chicken, caramelised onions and saffron.',       'price' => 14.99, 'availability' => 'available', 'emoji' => '🍛'],
                ['name' => 'Beef Karahi',       'description' => 'Tender beef cooked in a wok with tomatoes, green chillies and freshly ground spices.',      'price' => 16.99, 'availability' => 'available', 'emoji' => '🥘'],
                ['name' => 'Chicken Tikka',     'description' => 'Marinated chicken pieces grilled in a tandoor oven and served with mint chutney.',          'price' => 13.49, 'availability' => 'available', 'emoji' => '🍗'],
            ],
            'japanese' => [
                ['name' => 'Salmon Roll (8 pcs)',    'description' => 'Fresh Atlantic salmon with cucumber, avocado and seasoned sushi rice.',               'price' => 15.99, 'availability' => 'available', 'emoji' => '🍣'],
                ['name' => 'Spicy Tuna Roll (8 pcs)','description' => 'Sushi-grade tuna tossed in sriracha mayo with crispy tempura flakes.',                'price' => 14.99, 'availability' => 'available', 'emoji' => '🌶️'],
                ['name' => 'Dragon Roll (8 pcs)',    'description' => 'Prawn tempura inside, topped with thinly sliced avocado and sweet eel sauce.',        'price' => 17.99, 'availability' => 'seasonal',  'emoji' => '🐉'],
            ],
            'italian' => [
                ['name' => 'Margherita Pizza',     'description' => 'Wood-fired base with San Marzano tomato, fresh mozzarella and basil.',                  'price' => 11.99, 'availability' => 'available', 'emoji' => '🍕'],
                ['name' => 'Pepperoni Pizza',      'description' => 'Classic margherita topped with generous slices of spicy pepperoni.',                    'price' => 13.99, 'availability' => 'available', 'emoji' => '🍕'],
                ['name' => 'Spaghetti Carbonara',  'description' => 'Al dente spaghetti tossed with guanciale, egg yolk, pecorino and black pepper.',        'price' => 13.99, 'availability' => 'available', 'emoji' => '🍝'],
            ],
            'french' => [
                ['name' => 'Croque Monsieur',    'description' => 'Toasted brioche with Dijon béchamel, honey-glazed ham and melted Gruyère.',              'price' => 12.49, 'availability' => 'available', 'emoji' => '🥪'],
                ['name' => 'French Onion Soup',  'description' => 'Slow-caramelised onion broth topped with a crouton and bubbling Gruyère crust.',         'price' => 10.99, 'availability' => 'available', 'emoji' => '🍲'],
                ['name' => 'Beef Bourguignon',   'description' => 'Slow-braised beef in Burgundy wine with pearl onions, mushrooms and lardons.',           'price' => 18.99, 'availability' => 'available', 'emoji' => '🍖'],
            ],
        ],
        'desserts' => [
            'pakistani' => [
                ['name' => 'Gulab Jamun',        'description' => 'Soft milk-solid dumplings soaked in rose-flavoured sugar syrup, served warm.',           'price' => 5.99,  'availability' => 'available', 'emoji' => '🟤'],
            ],
            'japanese' => [
                ['name' => 'Mochi Ice Cream',    'description' => 'Hand-rolled sticky rice cakes filled with premium matcha or strawberry ice cream.',       'price' => 6.49,  'availability' => 'available', 'emoji' => '🍡'],
            ],
            'italian'  => [],
            'french'   => [
                ['name' => 'Crème Brûlée',       'description' => 'Silky vanilla custard with a perfectly torched caramelised sugar crust.',                'price' => 8.49,  'availability' => 'available', 'emoji' => '🍮'],
                ['name' => 'Chocolate Lava Cake','description' => 'Warm dark-chocolate fondant with a molten centre, served with vanilla ice cream.',        'price' => 7.99,  'availability' => 'available', 'emoji' => '🍫'],
            ],
        ],
        'drinks' => [
            'pakistani' => [
                ['name' => 'Mango Lassi',        'description' => 'Chilled blended drink of ripe Alphonso mango, full-fat yoghurt and a pinch of cardamom.','price' => 4.99,  'availability' => 'available', 'emoji' => '🥭'],
            ],
            'japanese' => [
                ['name' => 'Matcha Latte',       'description' => 'Ceremonial-grade matcha whisked with steamed oat milk and a touch of honey.',            'price' => 5.49,  'availability' => 'available', 'emoji' => '🍵'],
            ],
            'italian'  => [],
            'french'   => [
                ['name' => 'Café au Lait',       'description' => 'Strong French-press coffee topped with steamed whole milk, served in a wide bowl.',      'price' => 4.49,  'availability' => 'available', 'emoji' => '☕'],
                ['name' => 'Fresh Lemonade',     'description' => 'Hand-squeezed lemonade with cane sugar, mint and a splash of sparkling water.',          'price' => 3.99,  'availability' => 'available', 'emoji' => '🍋'],
            ],
        ],
    ];

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function cuisines(Request $request, Response $response, array $args): Response
    {
        $categorySlug = strtolower($args['category']);

        if (!isset($this->categories[$categorySlug])) {
            throw new HttpNotFoundException($request, "Category not found.");
        }

        $category = $this->categories[$categorySlug];

        $cuisines = [];
        foreach ($this->cuisines as $slug => $cuisine) {
            $dishCount = count($this->dishes[$categorySlug][$slug] ?? []);
            if ($dishCount > 0) {
                $cuisines[] = array_merge($cuisine, ['dish_count' => $dishCount]);
            }
        }

        return $this->render($response, 'browse/cuisines.twig', [
            'category' => $category,
            'cuisines' => $cuisines,
        ]);
    }

    public function dishes(Request $request, Response $response, array $args): Response
    {
        $categorySlug = strtolower($args['category']);
        $cuisineSlug  = strtolower($args['cuisine']);

        if (!isset($this->categories[$categorySlug])) {
            throw new HttpNotFoundException($request, "Category not found.");
        }
        if (!isset($this->cuisines[$cuisineSlug])) {
            throw new HttpNotFoundException($request, "Cuisine not found.");
        }

        $dishes = $this->dishes[$categorySlug][$cuisineSlug] ?? [];

        return $this->render($response, 'browse/dishes.twig', [
            'category' => $this->categories[$categorySlug],
            'cuisine'  => $this->cuisines[$cuisineSlug],
            'dishes'   => $dishes,
        ]);
    }
}
