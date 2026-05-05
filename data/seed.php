<?php

/**
 * seed.php - Database seeder
 *
 * WHAT: Creates all tables and fills them with realistic sample data.
 * HOW:  Run this from the project root: php data/seed.php
 *
 * IMPORTANT: Table names are lowercase and primary keys are named "id"
 *            so they match what RedBeanPHP expects by default.
 */

require_once __DIR__ . '/../vendor/autoload.php';

define('APP_ROOT_DIR_NAME', 'cravecart-ecommerce');
define('APP_BASE_DIR_PATH', __DIR__ . '/..');

$db = require __DIR__ . '/../config/env.php';

try {
    $pdoNoDb = new PDO(
        sprintf('mysql:host=%s;charset=utf8mb4', $db['host']),
        $db['username'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdoNoDb->exec("CREATE DATABASE IF NOT EXISTS `{$db['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[ok] Database '{$db['database']}' ready.\n";

    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['database']),
        $db['username'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "[ok] Connected to database.\n\n";

    // ---------------------------------------------------------------------
    // RESET SCHEMA
    // ---------------------------------------------------------------------
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['order_dish', 'orders', 'saved_cart', 'delivery_address', 'dishes', 'categories', 'cuisines', 'users'] as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "[ok] Reset existing schema.\n";

    // ---------------------------------------------------------------------
    // CREATE TABLES
    // ---------------------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            name          VARCHAR(100)  NOT NULL,
            email         VARCHAR(150)  NOT NULL UNIQUE,
            password_hash VARCHAR(255)  NOT NULL,
            totp_secret   VARCHAR(255),
            role          VARCHAR(20)   NOT NULL DEFAULT 'client',
            created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "[ok] Table: users\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cuisines (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100) NOT NULL UNIQUE,
            code        VARCHAR(5)   NOT NULL,
            slug        VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255),
            image_url   VARCHAR(500)
        )
    ");
    echo "[ok] Table: cuisines\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255)
        )
    ");
    echo "[ok] Table: categories\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dishes (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            category_id  INT NOT NULL,
            cuisine_id   INT NOT NULL,
            name         VARCHAR(150) NOT NULL,
            slug         VARCHAR(150),
            description  VARCHAR(500),
            price        DECIMAL(10,2) NOT NULL,
            image_url    VARCHAR(500),
            availability VARCHAR(20) NOT NULL DEFAULT 'available',
            CONSTRAINT fk_dishes_categories FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
            CONSTRAINT fk_dishes_cuisines   FOREIGN KEY (cuisine_id)  REFERENCES cuisines(id) ON DELETE CASCADE
        )
    ");
    echo "[ok] Table: dishes\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS delivery_address (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT          NOT NULL,
            street      VARCHAR(255) NOT NULL,
            city        VARCHAR(100) NOT NULL,
            postal_code VARCHAR(20)  NOT NULL,
            CONSTRAINT fk_delivery_address_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "[ok] Table: delivery_address\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT            NOT NULL,
            address_id INT            NOT NULL,
            subtotal   DECIMAL(10,2)  NOT NULL,
            taxes      DECIMAL(10,2)  NOT NULL,
            total      DECIMAL(10,2)  NOT NULL,
            status     VARCHAR(20)    NOT NULL DEFAULT 'pending',
            notes      VARCHAR(500),
            ordered_at DATETIME       DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_orders_users            FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_orders_delivery_address FOREIGN KEY (address_id) REFERENCES delivery_address(id) ON DELETE CASCADE
        )
    ");
    echo "[ok] Table: orders\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_dish (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            order_id   INT           NOT NULL,
            dish_id    INT           NOT NULL,
            quantity   INT           NOT NULL,
            item_price DECIMAL(10,2) NOT NULL,
            CONSTRAINT fk_order_dish_orders FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_order_dish_dishes FOREIGN KEY (dish_id)  REFERENCES dishes(id) ON DELETE CASCADE
        )
    ");
    echo "[ok] Table: order_dish\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS saved_cart (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT           NOT NULL,
            dish_id    INT           NOT NULL,
            quantity   INT           NOT NULL,
            dish_price DECIMAL(10,2) NOT NULL,
            saved_at   DATETIME      DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_saved_cart_users  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_saved_cart_dishes FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE
        )
    ");
    echo "[ok] Table: saved_cart\n";

    echo "\n[ok] All tables created.\n\n";

    // ---------------------------------------------------------------------
    // SEED: users
    // ---------------------------------------------------------------------
    $users = [
        ['name' => 'Admin User',    'email' => 'admin@cravecart.com', 'password_hash' => password_hash('admin1234', PASSWORD_BCRYPT),   'role' => 'administrator'],
        ['name' => 'Alice Johnson', 'email' => 'alice@example.com',   'password_hash' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'client'],
        ['name' => 'Bob Smith',     'email' => 'bob@example.com',     'password_hash' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'client'],
        ['name' => 'Carol White',   'email' => 'carol@example.com',   'password_hash' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'client'],
        ['name' => 'Daniel Lee',    'email' => 'daniel@example.com',  'password_hash' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'client'],
        ['name' => 'Emma Wilson',   'email' => 'emma@example.com',    'password_hash' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'client'],
        ['name' => 'Farah Khan',    'email' => 'farah@example.com',   'password_hash' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'client'],
    ];

    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)');
    foreach ($users as $row) {
        $stmt->execute($row);
    }
    echo '[ok] Seeded ' . count($users) . " users.\n";

    $userIds = $pdo->query('SELECT email, id FROM users')->fetchAll(PDO::FETCH_KEY_PAIR);

    // ---------------------------------------------------------------------
    // SEED: cuisines
    // ---------------------------------------------------------------------
    $cuisines = [
        ['name' => 'Chinese',  'code' => 'CN', 'slug' => 'chinese',  'description' => 'Bold flavors with noodles, rice, and dim sum favorites.',            'image_url' => 'https://images.unsplash.com/photo-1563245372-f21724e3856d?w=400&q=70'],
        ['name' => 'Japanese', 'code' => 'JP', 'slug' => 'japanese', 'description' => 'Fresh sushi, warming bowls, and clean seasonal flavors.',            'image_url' => 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=400&q=70'],
        ['name' => 'Mexican',  'code' => 'MX', 'slug' => 'mexican',  'description' => 'Colorful street-food classics with heat, crunch, and lime.',         'image_url' => 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?w=400&q=70'],
        ['name' => 'Italian',  'code' => 'IT', 'slug' => 'italian',  'description' => 'Comforting pastas, pizzas, and cafe-style desserts.',               'image_url' => 'https://images.unsplash.com/photo-1555949258-eb67b1ef0ceb?w=400&q=70'],
        ['name' => 'Indian',   'code' => 'IN', 'slug' => 'indian',   'description' => 'Aromatic curries, grilled specialties, and cooling drinks.',        'image_url' => 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=400&q=70'],
        ['name' => 'Lebanese', 'code' => 'LB', 'slug' => 'lebanese', 'description' => 'Fresh mezze, shawarma, grilled meats, and bright herbs.',           'image_url' => 'https://images.unsplash.com/photo-1541518763669-27fef04b14ea?w=400&q=70'],
        ['name' => 'American', 'code' => 'US', 'slug' => 'american', 'description' => 'Classic comfort food, smoky barbecue, and diner-style treats.',     'image_url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400&q=70'],
        ['name' => 'Thai',     'code' => 'TH', 'slug' => 'thai',     'description' => 'Sweet, spicy, and fragrant dishes balanced with herbs and citrus.', 'image_url' => 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=400&q=70'],
    ];

    $stmt = $pdo->prepare('INSERT INTO cuisines (name, code, slug, description, image_url) VALUES (:name, :code, :slug, :description, :image_url)');
    foreach ($cuisines as $row) {
        $stmt->execute($row);
    }
    echo '[ok] Seeded ' . count($cuisines) . " cuisines.\n";

    $cuisineIds = $pdo->query('SELECT slug, id FROM cuisines')->fetchAll(PDO::FETCH_KEY_PAIR);

    // ---------------------------------------------------------------------
    // SEED: categories
    // ---------------------------------------------------------------------
    $categories = [
        ['name' => 'Food',     'description' => 'Main dishes from around the world'],
        ['name' => 'Desserts', 'description' => 'Sweet treats and bakery favorites'],
        ['name' => 'Drinks',   'description' => 'Cold and hot beverages for every craving'],
    ];

    $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (:name, :description)');
    foreach ($categories as $row) {
        $stmt->execute($row);
    }
    echo '[ok] Seeded ' . count($categories) . " categories.\n";

    $categoryIds = $pdo->query('SELECT name, id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);

    // ---------------------------------------------------------------------
    // SEED: dishes
    // Each cuisine gets strong coverage across Food, Desserts, and Drinks so
    // every browse tab feels complete.
    // ---------------------------------------------------------------------
    $dishes = [
        ['category_name' => 'Food',     'cuisine_slug' => 'chinese',  'name' => 'Kung Pao Chicken',               'slug' => 'kung-pao-chicken',               'description' => 'Spicy wok-tossed chicken with peanuts, peppers, and a glossy chili sauce.',         'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'chinese',  'name' => 'Beef Fried Rice',                 'slug' => 'beef-fried-rice',                 'description' => 'Savory fried rice with seared beef, egg, scallions, and soy-garlic flavor.',       'price' => 11.99, 'image_url' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'chinese',  'name' => 'Shrimp Lo Mein',                  'slug' => 'shrimp-lo-mein',                  'description' => 'Soft egg noodles tossed with shrimp, vegetables, and a rich wok sauce.',             'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=400&q=70', 'availability' => 'seasonal'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'chinese',  'name' => 'Sesame Balls',                    'slug' => 'sesame-balls',                    'description' => 'Golden fried rice dough filled with sweet red bean paste.',                          'price' => 5.49,  'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Ciput%20sesame%20balls.jpg', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'chinese',  'name' => 'Bubble Milk Tea',                 'slug' => 'bubble-milk-tea',                 'description' => 'Black tea with creamy milk and chewy tapioca pearls.',                                'price' => 5.99,  'image_url' => 'https://images.unsplash.com/photo-1558857563-b371033873b8?w=400&q=70', 'availability' => 'available'],

        ['category_name' => 'Food',     'cuisine_slug' => 'japanese', 'name' => 'Salmon Roll (8 pcs)',            'slug' => 'salmon-roll',                     'description' => 'Fresh salmon, cucumber, and avocado wrapped in seasoned rice and nori.',             'price' => 15.99, 'image_url' => 'https://plus.unsplash.com/premium_photo-1668143360914-d8905f8b2bd1?q=80&w=774&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'japanese', 'name' => 'Chicken Ramen',                   'slug' => 'chicken-ramen',                   'description' => 'Rich broth, springy noodles, grilled chicken, soft egg, and sesame oil.',            'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1680593180878-e0cd1e99486e?q=80&w=1160&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'japanese', 'name' => 'Katsu Curry',                     'slug' => 'katsu-curry',                     'description' => 'Crispy chicken cutlet served over rice with silky Japanese curry sauce.',            'price' => 15.49, 'image_url' => 'https://images.unsplash.com/photo-1534422298391-e4f8c172dddb?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'japanese', 'name' => 'Mochi Ice Cream',                'slug' => 'mochi-ice-cream',                 'description' => 'Chewy mochi shells filled with smooth vanilla ice cream.',                            'price' => 6.49,  'image_url' => 'https://plus.unsplash.com/premium_photo-1701104846200-9cbfccc8a457?q=80&w=774&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'japanese', 'name' => 'Matcha Latte',                   'slug' => 'matcha-latte',                    'description' => 'Stone-ground green tea blended with steamed milk.',                                  'price' => 5.49,  'image_url' => 'https://images.unsplash.com/photo-1515823064-d6e0c04616a7?q=80&w=1742&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],

        ['category_name' => 'Food',     'cuisine_slug' => 'mexican',  'name' => 'Beef Tacos (3 pcs)',             'slug' => 'beef-tacos',                      'description' => 'Corn tortillas packed with seasoned beef, salsa roja, and crema.',                  'price' => 12.99, 'image_url' => 'https://images.unsplash.com/photo-1551504734-5ee1c4a1479b?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'mexican',  'name' => 'Chicken Burrito',                 'slug' => 'chicken-burrito',                 'description' => 'A loaded burrito with grilled chicken, rice, beans, cheese, and guacamole.',        'price' => 13.49, 'image_url' => 'https://images.unsplash.com/photo-1626700051175-6818013e1d4f?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'mexican',  'name' => 'Birria Quesadilla',               'slug' => 'birria-quesadilla',               'description' => 'Cheesy griddled tortilla stuffed with juicy birria beef and onions.',               'price' => 14.99, 'image_url' => 'https://images.unsplash.com/photo-1613514785940-daed07799d9b?w=400&q=70', 'availability' => 'seasonal'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'mexican',  'name' => 'Churros',                         'slug' => 'churros',                         'description' => 'Warm cinnamon sugar churros served with chocolate dipping sauce.',                   'price' => 6.49,  'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Churros%20%284519186101%29.jpg', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'mexican',  'name' => 'Horchata',                        'slug' => 'horchata',                        'description' => 'Sweet rice drink with cinnamon, vanilla, and a creamy finish.',                      'price' => 3.99,  'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Vaso%20de%20horchata.png', 'availability' => 'available'],

        ['category_name' => 'Food',     'cuisine_slug' => 'italian',  'name' => 'Margherita Pizza',               'slug' => 'margherita-pizza',                'description' => 'Wood-fired pizza with tomato sauce, mozzarella, basil, and olive oil.',             'price' => 11.99, 'image_url' => 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'italian',  'name' => 'Spaghetti Carbonara',            'slug' => 'spaghetti-carbonara',             'description' => 'Silky spaghetti finished with pancetta, egg yolk, parmesan, and black pepper.',     'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'italian',  'name' => 'Truffle Mushroom Risotto',       'slug' => 'truffle-mushroom-risotto',        'description' => 'Creamy arborio rice with roasted mushrooms, parmesan, and truffle aroma.',          'price' => 16.49, 'image_url' => 'https://images.unsplash.com/photo-1473093295043-cdd812d0e601?w=400&q=70', 'availability' => 'seasonal'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'italian',  'name' => 'Tiramisu',                       'slug' => 'tiramisu',                        'description' => 'Espresso-soaked layers with mascarpone cream and cocoa.',                            'price' => 7.99,  'image_url' => 'https://images.unsplash.com/photo-1639744211487-b27e3551b07c?q=80&w=870&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'italian',  'name' => 'Sparkling Blood Orange Soda',    'slug' => 'sparkling-blood-orange-soda',     'description' => 'Citrusy Italian soda with bright blood orange flavor and bubbles.',                  'price' => 4.99,  'image_url' => 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=400&q=70', 'availability' => 'available'],

        ['category_name' => 'Food',     'cuisine_slug' => 'indian',   'name' => 'Chicken Biryani',                'slug' => 'chicken-biryani',                 'description' => 'Fragrant basmati rice layered with spiced chicken, saffron, and herbs.',            'price' => 14.99, 'image_url' => 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'indian',   'name' => 'Butter Chicken',                 'slug' => 'butter-chicken',                  'description' => 'Tender chicken simmered in a creamy tomato-butter curry sauce.',                    'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1588166524941-3bf61a9c41db?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'indian',   'name' => 'Paneer Tikka Masala',            'slug' => 'paneer-tikka-masala',             'description' => 'Charred paneer cubes in a tomato-onion masala with warming spices.',                'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'indian',   'name' => 'Gulab Jamun',                    'slug' => 'gulab-jamun',                     'description' => 'Soft fried dumplings soaked in cardamom and rose syrup.',                           'price' => 5.99,  'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Gulab%20Jamuns.jpg', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'indian',   'name' => 'Mango Lassi',                    'slug' => 'mango-lassi',                     'description' => 'Chilled mango yogurt drink with a silky, refreshing finish.',                       'price' => 4.99,  'image_url' => 'https://images.unsplash.com/photo-1623065422902-30a2d299bbe4?q=80&w=774&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],

        ['category_name' => 'Food',     'cuisine_slug' => 'lebanese', 'name' => 'Chicken Shawarma',               'slug' => 'chicken-shawarma',                'description' => 'Juicy marinated chicken wrapped with garlic sauce, pickles, and lettuce.',          'price' => 12.49, 'image_url' => 'https://images.unsplash.com/photo-1676300187013-7540d4e9440d?q=80&w=1740&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'lebanese', 'name' => 'Falafel Plate',                  'slug' => 'falafel-plate',                   'description' => 'Crispy falafel with hummus, tabbouleh, pickles, and warm pita.',                    'price' => 11.99, 'image_url' => 'https://images.unsplash.com/photo-1593001872095-7d5b3868fb1d?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'lebanese', 'name' => 'Kafta Skewers',                  'slug' => 'kafta-skewers',                   'description' => 'Chargrilled beef kafta with rice, grilled vegetables, and tahini.',                 'price' => 15.49, 'image_url' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=400&q=70', 'availability' => 'seasonal'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'lebanese', 'name' => 'Baklava',                        'slug' => 'baklava',                         'description' => 'Flaky pastry layers filled with pistachios and honey syrup.',                       'price' => 6.49,  'image_url' => 'https://images.unsplash.com/photo-1519676867240-f03562e64548?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'lebanese', 'name' => 'Mint Lemonade',                  'slug' => 'mint-lemonade',                   'description' => 'Fresh lemon juice blended with mint leaves and crushed ice.',                       'price' => 4.49,  'image_url' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=400&q=70', 'availability' => 'available'],

        ['category_name' => 'Food',     'cuisine_slug' => 'american', 'name' => 'Classic Cheeseburger',           'slug' => 'classic-cheeseburger',            'description' => 'Grilled beef patty with cheddar, pickles, lettuce, tomato, and sauce.',            'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'american', 'name' => 'BBQ Ribs',                       'slug' => 'bbq-ribs',                        'description' => 'Slow-cooked ribs glazed with smoky barbecue sauce and charred edges.',              'price' => 18.99, 'image_url' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'american', 'name' => 'Nashville Hot Chicken Sandwich', 'slug' => 'nashville-hot-chicken-sandwich',  'description' => 'Crispy spicy chicken sandwich with slaw, pickles, and toasted brioche.',           'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'american', 'name' => 'New York Cheesecake',            'slug' => 'new-york-cheesecake',             'description' => 'Dense creamy cheesecake finished with a buttery graham crust.',                     'price' => 7.49,  'image_url' => 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'american', 'name' => 'Vanilla Cream Soda',             'slug' => 'vanilla-cream-soda',              'description' => 'Old-school cream soda with vanilla sweetness and fizzy lift.',                      'price' => 3.99,  'image_url' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=400&q=70', 'availability' => 'unavailable'],

        ['category_name' => 'Food',     'cuisine_slug' => 'thai',     'name' => 'Pad Thai',                       'slug' => 'pad-thai',                        'description' => 'Rice noodles stir-fried with tamarind, peanuts, and lime.',                         'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'thai',     'name' => 'Green Curry',                    'slug' => 'green-curry',                     'description' => 'Creamy coconut curry with chicken, Thai basil, and tender vegetables.',             'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1455619452474-d2be8b1e70cd?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'thai',     'name' => 'Thai Basil Beef',                'slug' => 'thai-basil-beef',                 'description' => 'Savory beef stir-fry with chilies, garlic, basil, and jasmine rice.',              'price' => 15.29, 'image_url' => 'https://images.unsplash.com/photo-1569562211093-4ed0d0758f12?w=400&q=70', 'availability' => 'seasonal'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'thai',     'name' => 'Mango Sticky Rice',              'slug' => 'mango-sticky-rice',               'description' => 'Sweet glutinous rice topped with ripe mango and coconut cream.',                    'price' => 6.99,  'image_url' => 'https://images.unsplash.com/photo-1711161988375-da7eff032e45?q=80&w=1740&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'thai',     'name' => 'Thai Iced Tea',                  'slug' => 'thai-iced-tea',                   'description' => 'Sweet spiced black tea with condensed milk poured over ice.',                       'price' => 4.79,  'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Thai%20iced%20tea.jpg', 'availability' => 'available'],
    ];

    $stmt = $pdo->prepare('INSERT INTO dishes (category_id, cuisine_id, name, slug, description, price, image_url, availability) VALUES (:category_id, :cuisine_id, :name, :slug, :description, :price, :image_url, :availability)');
    $dishPrices = [];
    foreach ($dishes as $row) {
        $stmt->execute([
            'category_id' => $categoryIds[$row['category_name']],
            'cuisine_id' => $cuisineIds[$row['cuisine_slug']],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'description' => $row['description'],
            'price' => $row['price'],
            'image_url' => $row['image_url'],
            'availability' => $row['availability'],
        ]);
        $dishPrices[$row['slug']] = (float) $row['price'];
    }
    echo '[ok] Seeded ' . count($dishes) . " dishes.\n";

    $dishIds = $pdo->query('SELECT slug, id FROM dishes')->fetchAll(PDO::FETCH_KEY_PAIR);

    // ---------------------------------------------------------------------
    // SEED: delivery addresses
    // ---------------------------------------------------------------------
    $addresses = [
        ['key' => 'alice_home',  'user_email' => 'alice@example.com',  'street' => '123 Maple Street',    'city' => 'Montreal', 'postal_code' => 'H2X 1Y4'],
        ['key' => 'bob_home',    'user_email' => 'bob@example.com',    'street' => '456 Oak Avenue',      'city' => 'Gatineau', 'postal_code' => 'J8T 3R2'],
        ['key' => 'carol_home',  'user_email' => 'carol@example.com',  'street' => '789 Pine Boulevard',  'city' => 'Ottawa',   'postal_code' => 'K1A 0A6'],
        ['key' => 'daniel_home', 'user_email' => 'daniel@example.com', 'street' => '82 Cedar Crescent',   'city' => 'Toronto',  'postal_code' => 'M5V 2T6'],
        ['key' => 'emma_home',   'user_email' => 'emma@example.com',   'street' => '15 Willow Lane',      'city' => 'Laval',    'postal_code' => 'H7N 4S2'],
        ['key' => 'farah_home',  'user_email' => 'farah@example.com',  'street' => '240 Riverside Drive', 'city' => 'Quebec',   'postal_code' => 'G1R 5M1'],
    ];

    $stmt = $pdo->prepare('INSERT INTO delivery_address (user_id, street, city, postal_code) VALUES (:user_id, :street, :city, :postal_code)');
    $addressIds = [];
    foreach ($addresses as $row) {
        $stmt->execute([
            'user_id' => $userIds[$row['user_email']],
            'street' => $row['street'],
            'city' => $row['city'],
            'postal_code' => $row['postal_code'],
        ]);
        $addressIds[$row['key']] = (int) $pdo->lastInsertId();
    }
    echo '[ok] Seeded ' . count($addresses) . " delivery addresses.\n";

    // ---------------------------------------------------------------------
    // SEED: orders + order_dish
    // ---------------------------------------------------------------------
    $orders = [
        [
            'user_email' => 'alice@example.com',
            'address_key' => 'alice_home',
            'status' => 'delivered',
            'notes' => 'Leave at the door.',
            'ordered_at' => '2026-05-01 18:40:00',
            'items' => [
                ['slug' => 'butter-chicken', 'quantity' => 1],
                ['slug' => 'mango-lassi', 'quantity' => 1],
                ['slug' => 'gulab-jamun', 'quantity' => 1],
            ],
        ],
        [
            'user_email' => 'alice@example.com',
            'address_key' => 'alice_home',
            'status' => 'processing',
            'notes' => 'Buzz unit 302 on arrival.',
            'ordered_at' => '2026-05-04 12:15:00',
            'items' => [
                ['slug' => 'chicken-ramen', 'quantity' => 1],
                ['slug' => 'matcha-latte', 'quantity' => 1],
                ['slug' => 'mochi-ice-cream', 'quantity' => 1],
            ],
        ],
        [
            'user_email' => 'bob@example.com',
            'address_key' => 'bob_home',
            'status' => 'shipped',
            'notes' => 'Call when outside.',
            'ordered_at' => '2026-05-03 19:05:00',
            'items' => [
                ['slug' => 'classic-cheeseburger', 'quantity' => 2],
                ['slug' => 'new-york-cheesecake', 'quantity' => 1],
            ],
        ],
        [
            'user_email' => 'carol@example.com',
            'address_key' => 'carol_home',
            'status' => 'pending',
            'notes' => 'Extra napkins please.',
            'ordered_at' => '2026-05-05 11:25:00',
            'items' => [
                ['slug' => 'margherita-pizza', 'quantity' => 1],
                ['slug' => 'tiramisu', 'quantity' => 1],
            ],
        ],
        [
            'user_email' => 'daniel@example.com',
            'address_key' => 'daniel_home',
            'status' => 'wrapping',
            'notes' => null,
            'ordered_at' => '2026-05-05 13:55:00',
            'items' => [
                ['slug' => 'pad-thai', 'quantity' => 1],
                ['slug' => 'thai-iced-tea', 'quantity' => 1],
                ['slug' => 'mango-sticky-rice', 'quantity' => 1],
            ],
        ],
        [
            'user_email' => 'emma@example.com',
            'address_key' => 'emma_home',
            'status' => 'delivered',
            'notes' => 'Please ring the side door bell.',
            'ordered_at' => '2026-04-28 17:30:00',
            'items' => [
                ['slug' => 'chicken-shawarma', 'quantity' => 1],
                ['slug' => 'baklava', 'quantity' => 1],
                ['slug' => 'mint-lemonade', 'quantity' => 1],
            ],
        ],
        [
            'user_email' => 'farah@example.com',
            'address_key' => 'farah_home',
            'status' => 'processing',
            'notes' => 'No cutlery needed.',
            'ordered_at' => '2026-05-05 16:10:00',
            'items' => [
                ['slug' => 'paneer-tikka-masala', 'quantity' => 1],
                ['slug' => 'gulab-jamun', 'quantity' => 1],
                ['slug' => 'mango-lassi', 'quantity' => 1],
            ],
        ],
        [
            'user_email' => 'bob@example.com',
            'address_key' => 'bob_home',
            'status' => 'delivered',
            'notes' => null,
            'ordered_at' => '2026-04-22 20:00:00',
            'items' => [
                ['slug' => 'beef-tacos', 'quantity' => 1],
                ['slug' => 'horchata', 'quantity' => 1],
                ['slug' => 'churros', 'quantity' => 1],
            ],
        ],
    ];

    $orderStmt = $pdo->prepare('INSERT INTO orders (user_id, address_id, subtotal, taxes, total, status, notes, ordered_at) VALUES (:user_id, :address_id, :subtotal, :taxes, :total, :status, :notes, :ordered_at)');
    $orderDishStmt = $pdo->prepare('INSERT INTO order_dish (order_id, dish_id, quantity, item_price) VALUES (:order_id, :dish_id, :quantity, :item_price)');

    $seededOrderCount = 0;
    $seededOrderItemCount = 0;

    foreach ($orders as $order) {
        $subtotal = 0.0;
        foreach ($order['items'] as $item) {
            $subtotal += $dishPrices[$item['slug']] * $item['quantity'];
        }

        $subtotal = round($subtotal, 2);
        $taxes = round($subtotal * 0.13, 2);
        $total = round($subtotal + $taxes, 2);

        $orderStmt->execute([
            'user_id' => $userIds[$order['user_email']],
            'address_id' => $addressIds[$order['address_key']],
            'subtotal' => $subtotal,
            'taxes' => $taxes,
            'total' => $total,
            'status' => $order['status'],
            'notes' => $order['notes'],
            'ordered_at' => $order['ordered_at'],
        ]);

        $orderId = (int) $pdo->lastInsertId();
        $seededOrderCount++;

        foreach ($order['items'] as $item) {
            $orderDishStmt->execute([
                'order_id' => $orderId,
                'dish_id' => $dishIds[$item['slug']],
                'quantity' => $item['quantity'],
                'item_price' => $dishPrices[$item['slug']],
            ]);
            $seededOrderItemCount++;
        }
    }

    echo '[ok] Seeded ' . $seededOrderCount . " orders.\n";
    echo '[ok] Seeded ' . $seededOrderItemCount . " order items.\n";

    // ---------------------------------------------------------------------
    // SEED: saved_cart
    // ---------------------------------------------------------------------
    $savedCarts = [
        ['user_email' => 'alice@example.com', 'items' => [['slug' => 'pad-thai', 'quantity' => 1], ['slug' => 'thai-iced-tea', 'quantity' => 1]]],
        ['user_email' => 'bob@example.com',   'items' => [['slug' => 'bbq-ribs', 'quantity' => 1], ['slug' => 'new-york-cheesecake', 'quantity' => 2]]],
        ['user_email' => 'emma@example.com',  'items' => [['slug' => 'shrimp-lo-mein', 'quantity' => 1], ['slug' => 'bubble-milk-tea', 'quantity' => 1]]],
        ['user_email' => 'farah@example.com', 'items' => [['slug' => 'falafel-plate', 'quantity' => 1], ['slug' => 'mint-lemonade', 'quantity' => 1], ['slug' => 'baklava', 'quantity' => 1]]],
    ];

    $savedCartStmt = $pdo->prepare('INSERT INTO saved_cart (user_id, dish_id, quantity, dish_price) VALUES (:user_id, :dish_id, :quantity, :dish_price)');
    $savedCartCount = 0;
    foreach ($savedCarts as $cart) {
        foreach ($cart['items'] as $item) {
            $savedCartStmt->execute([
                'user_id' => $userIds[$cart['user_email']],
                'dish_id' => $dishIds[$item['slug']],
                'quantity' => $item['quantity'],
                'dish_price' => $dishPrices[$item['slug']],
            ]);
            $savedCartCount++;
        }
    }
    echo '[ok] Seeded ' . $savedCartCount . " saved cart items.\n\n";

    echo "Database seeded successfully.\n";
    echo str_repeat('-', 56) . "\n";
    echo "Admin:  admin@cravecart.com / admin1234\n";
    echo "Client: alice@example.com   / password123\n";
    echo "Client: bob@example.com     / password123\n";
    echo "Client: carol@example.com   / password123\n";
    echo "Client: daniel@example.com  / password123\n";
    echo "Client: emma@example.com    / password123\n";
    echo "Client: farah@example.com   / password123\n";
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
