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
    foreach (['notifications', 'order_dish', 'orders', 'saved_cart', 'delivery_address', 'dishes', 'categories', 'cuisines', 'users'] as $table) {
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
            id             INT AUTO_INCREMENT PRIMARY KEY,
            name           VARCHAR(100) NOT NULL UNIQUE,
            name_fr        VARCHAR(100),
            name_es        VARCHAR(100),
            code           VARCHAR(5)   NOT NULL,
            slug           VARCHAR(100) NOT NULL UNIQUE,
            description    VARCHAR(255),
            description_fr VARCHAR(255),
            description_es VARCHAR(255),
            image_url      VARCHAR(500)
        )
    ");
    echo "[ok] Table: cuisines\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100) NOT NULL UNIQUE,
            name_fr     VARCHAR(100),
            name_es     VARCHAR(100),
            description VARCHAR(255)
        )
    ");
    echo "[ok] Table: categories\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dishes (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            category_id    INT NOT NULL,
            cuisine_id     INT NOT NULL,
            name           VARCHAR(150) NOT NULL,
            name_fr        VARCHAR(150),
            name_es        VARCHAR(150),
            slug           VARCHAR(150),
            description    VARCHAR(500),
            description_fr VARCHAR(500),
            description_es VARCHAR(500),
            price          DECIMAL(10,2) NOT NULL,
            image_url      VARCHAR(500),
            availability   VARCHAR(20) NOT NULL DEFAULT 'available',
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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT          NOT NULL,
            message    VARCHAR(500) NOT NULL,
            is_read    TINYINT(1)   NOT NULL DEFAULT 0,
            created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_notifications_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "[ok] Table: notifications\n";

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
        ['name' => 'Chinese',  'name_fr' => 'Chinoise',      'name_es' => 'China',        'code' => 'CN', 'slug' => 'chinese',  'description' => 'Bold flavors with noodles, rice, and dim sum favorites.',            'description_fr' => 'Saveurs audacieuses avec nouilles, riz et favoris dim sum.',                                       'description_es' => 'Sabores audaces con fideos, arroz y los favoritos del dim sum.',                                    'image_url' => 'https://images.unsplash.com/photo-1563245372-f21724e3856d?w=400&q=70'],
        ['name' => 'Japanese', 'name_fr' => 'Japonaise',     'name_es' => 'Japonesa',     'code' => 'JP', 'slug' => 'japanese', 'description' => 'Fresh sushi, warming bowls, and clean seasonal flavors.',            'description_fr' => 'Sushi frais, bols réconfortants et saveurs saisonnières épurées.',                                 'description_es' => 'Sushi fresco, cuencos reconfortantes y sabores estacionales limpios.',                             'image_url' => 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=400&q=70'],
        ['name' => 'Mexican',  'name_fr' => 'Mexicaine',     'name_es' => 'Mexicana',     'code' => 'MX', 'slug' => 'mexican',  'description' => 'Colorful street-food classics with heat, crunch, and lime.',         'description_fr' => 'Classiques colorés de street food avec piquant, croquant et citron vert.',                         'description_es' => 'Clásicos coloridos de comida callejera con picante, crujiente y lima.',                            'image_url' => 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?w=400&q=70'],
        ['name' => 'Italian',  'name_fr' => 'Italienne',     'name_es' => 'Italiana',     'code' => 'IT', 'slug' => 'italian',  'description' => 'Comforting pastas, pizzas, and cafe-style desserts.',               'description_fr' => 'Pâtes réconfortantes, pizzas et desserts style café.',                                              'description_es' => 'Pastas reconfortantes, pizzas y postres estilo café.',                                              'image_url' => 'https://images.unsplash.com/photo-1555949258-eb67b1ef0ceb?w=400&q=70'],
        ['name' => 'Indian',   'name_fr' => 'Indienne',      'name_es' => 'India',        'code' => 'IN', 'slug' => 'indian',   'description' => 'Aromatic curries, grilled specialties, and cooling drinks.',        'description_fr' => 'Currys aromatiques, grillades et boissons rafraîchissantes.',                                      'description_es' => 'Curris aromáticos, especialidades a la parrilla y bebidas refrescantes.',                          'image_url' => 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=400&q=70'],
        ['name' => 'Lebanese', 'name_fr' => 'Libanaise',     'name_es' => 'Libanesa',     'code' => 'LB', 'slug' => 'lebanese', 'description' => 'Fresh mezze, shawarma, grilled meats, and bright herbs.',           'description_fr' => 'Mezze frais, chawarma, viandes grillées et herbes parfumées.',                                     'description_es' => 'Mezze fresco, shawarma, carnes a la parrilla y hierbas aromáticas.',                               'image_url' => 'https://images.unsplash.com/photo-1541518763669-27fef04b14ea?w=400&q=70'],
        ['name' => 'American', 'name_fr' => 'Américaine',    'name_es' => 'Americana',    'code' => 'US', 'slug' => 'american', 'description' => 'Classic comfort food, smoky barbecue, and diner-style treats.',     'description_fr' => 'Comfort food classique, barbecue fumé et spécialités de diner.',                                   'description_es' => 'Comida reconfortante clásica, barbacoa ahumada y delicias estilo diner.',                           'image_url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400&q=70'],
        ['name' => 'Thai',     'name_fr' => 'Thaïlandaise',  'name_es' => 'Tailandesa',   'code' => 'TH', 'slug' => 'thai',     'description' => 'Sweet, spicy, and fragrant dishes balanced with herbs and citrus.', 'description_fr' => 'Plats sucrés, épicés et parfumés, équilibrés avec herbes et agrumes.',                             'description_es' => 'Platos dulces, picantes y aromáticos equilibrados con hierbas y cítricos.',                        'image_url' => 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=400&q=70'],
    ];

    $stmt = $pdo->prepare('INSERT INTO cuisines (name, name_fr, name_es, code, slug, description, description_fr, description_es, image_url) VALUES (:name, :name_fr, :name_es, :code, :slug, :description, :description_fr, :description_es, :image_url)');
    foreach ($cuisines as $row) {
        $stmt->execute($row);
    }
    echo '[ok] Seeded ' . count($cuisines) . " cuisines.\n";

    $cuisineIds = $pdo->query('SELECT slug, id FROM cuisines')->fetchAll(PDO::FETCH_KEY_PAIR);

    // ---------------------------------------------------------------------
    // SEED: categories
    // ---------------------------------------------------------------------
    $categories = [
        ['name' => 'Food',     'name_fr' => 'Plats',    'name_es' => 'Comidas', 'description' => 'Main dishes from around the world'],
        ['name' => 'Desserts', 'name_fr' => 'Desserts', 'name_es' => 'Postres', 'description' => 'Sweet treats and bakery favorites'],
        ['name' => 'Drinks',   'name_fr' => 'Boissons', 'name_es' => 'Bebidas', 'description' => 'Cold and hot beverages for every craving'],
    ];

    $stmt = $pdo->prepare('INSERT INTO categories (name, name_fr, name_es, description) VALUES (:name, :name_fr, :name_es, :description)');
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
        // Chinese
        ['category_name' => 'Food',     'cuisine_slug' => 'chinese',  'name' => 'Kung Pao Chicken',               'name_fr' => 'Poulet Kung Pao',                      'name_es' => 'Pollo Kung Pao',                       'slug' => 'kung-pao-chicken',              'description' => 'Spicy wok-tossed chicken with peanuts, peppers, and a glossy chili sauce.',       'description_fr' => 'Poulet sauté épicé avec cacahuètes, poivrons et sauce chili brillante.',             'description_es' => 'Pollo salteado picante con cacahuetes, pimientos y una brillante salsa de chile.',          'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'chinese',  'name' => 'Beef Fried Rice',                 'name_fr' => 'Riz Sauté au Bœuf',                    'name_es' => 'Arroz Frito con Ternera',              'slug' => 'beef-fried-rice',               'description' => 'Savory fried rice with seared beef, egg, scallions, and soy-garlic flavor.',     'description_fr' => 'Riz frit savoureux avec bœuf saisi, œuf, ciboulette et saveurs soja-ail.',           'description_es' => 'Arroz frito sabroso con ternera a la plancha, huevo, cebollino y sabor a soja y ajo.',      'price' => 11.99, 'image_url' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'chinese',  'name' => 'Shrimp Lo Mein',                  'name_fr' => 'Lo Mein aux Crevettes',                'name_es' => 'Lo Mein de Gambas',                    'slug' => 'shrimp-lo-mein',                'description' => 'Soft egg noodles tossed with shrimp, vegetables, and a rich wok sauce.',          'description_fr' => 'Nouilles aux œufs tendres avec crevettes, légumes et riche sauce wok.',              'description_es' => 'Fideos de huevo suaves salteados con gambas, verduras y una rica salsa de wok.',           'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=400&q=70', 'availability' => 'seasonal'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'chinese',  'name' => 'Sesame Balls',                    'name_fr' => 'Boules de Sésame',                     'name_es' => 'Bolitas de Sésamo',                    'slug' => 'sesame-balls',                  'description' => 'Golden fried rice dough filled with sweet red bean paste.',                       'description_fr' => 'Pâte de riz dorée et frite, fourrée de pâte de haricots rouges sucrée.',             'description_es' => 'Masa de arroz dorada y frita rellena de dulce pasta de judías rojas.',                     'price' => 5.49,  'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Ciput%20sesame%20balls.jpg', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'chinese',  'name' => 'Bubble Milk Tea',                 'name_fr' => 'Thé au Lait et Tapioca',               'name_es' => 'Té de Leche con Tapioca',              'slug' => 'bubble-milk-tea',               'description' => 'Black tea with creamy milk and chewy tapioca pearls.',                             'description_fr' => 'Thé noir avec lait crémeux et perles de tapioca à mâcher.',                          'description_es' => 'Té negro con leche cremosa y bolitas de tapioca para masticar.',                           'price' => 5.99,  'image_url' => 'https://images.unsplash.com/photo-1558857563-b371033873b8?w=400&q=70', 'availability' => 'available'],
        // Japanese
        ['category_name' => 'Food',     'cuisine_slug' => 'japanese', 'name' => 'Salmon Roll (8 pcs)',             'name_fr' => 'Rouleau de Saumon (8 pcs)',             'name_es' => 'Rollo de Salmón (8 pcs)',              'slug' => 'salmon-roll',                   'description' => 'Fresh salmon, cucumber, and avocado wrapped in seasoned rice and nori.',          'description_fr' => 'Saumon frais, concombre et avocat enveloppés dans du riz assaisonné et du nori.',    'description_es' => 'Salmón fresco, pepino y aguacate envueltos en arroz sazonado y nori.',                     'price' => 15.99, 'image_url' => 'https://plus.unsplash.com/premium_photo-1668143360914-d8905f8b2bd1?q=80&w=774&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'japanese', 'name' => 'Chicken Ramen',                   'name_fr' => 'Ramen au Poulet',                      'name_es' => 'Ramen de Pollo',                       'slug' => 'chicken-ramen',                 'description' => 'Rich broth, springy noodles, grilled chicken, soft egg, and sesame oil.',         'description_fr' => 'Bouillon riche, nouilles élastiques, poulet grillé, œuf mollet et huile de sésame.',  'description_es' => 'Caldo rico, fideos elásticos, pollo a la parrilla, huevo blando y aceite de sésamo.',      'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1680593180878-e0cd1e99486e?q=80&w=1160&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'japanese', 'name' => 'Katsu Curry',                     'name_fr' => 'Katsu Curry',                          'name_es' => 'Katsu Curry',                          'slug' => 'katsu-curry',                   'description' => 'Crispy chicken cutlet served over rice with silky Japanese curry sauce.',         'description_fr' => 'Escalope de poulet croustillante servie sur du riz avec une sauce curry japonaise.',  'description_es' => 'Escalope de pollo crujiente servida sobre arroz con una suave salsa de curry japonés.',    'price' => 15.49, 'image_url' => 'https://images.unsplash.com/photo-1534422298391-e4f8c172dddb?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'japanese', 'name' => 'Mochi Ice Cream',                 'name_fr' => 'Glace Mochi',                          'name_es' => 'Helado Mochi',                         'slug' => 'mochi-ice-cream',               'description' => 'Chewy mochi shells filled with smooth vanilla ice cream.',                        'description_fr' => 'Enveloppes de mochi moelleuses garnies de glace à la vanille onctueuse.',            'description_es' => 'Envolturas de mochi masticables rellenas de suave helado de vainilla.',                    'price' => 6.49,  'image_url' => 'https://plus.unsplash.com/premium_photo-1701104846200-9cbfccc8a457?q=80&w=774&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'japanese', 'name' => 'Matcha Latte',                    'name_fr' => 'Latte Matcha',                         'name_es' => 'Latte de Matcha',                      'slug' => 'matcha-latte',                  'description' => 'Stone-ground green tea blended with steamed milk.',                                'description_fr' => 'Thé vert moulu à la pierre mélangé à du lait vapeur.',                               'description_es' => 'Té verde molido en piedra mezclado con leche al vapor.',                                   'price' => 5.49,  'image_url' => 'https://images.unsplash.com/photo-1515823064-d6e0c04616a7?q=80&w=1742&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        // Mexican
        ['category_name' => 'Food',     'cuisine_slug' => 'mexican',  'name' => 'Beef Tacos (3 pcs)',              'name_fr' => 'Tacos au Bœuf (3 pcs)',                'name_es' => 'Tacos de Ternera (3 pcs)',             'slug' => 'beef-tacos',                    'description' => 'Corn tortillas packed with seasoned beef, salsa roja, and crema.',                'description_fr' => 'Tortillas de maïs garnies de bœuf assaisonné, salsa roja et crème.',                'description_es' => 'Tortillas de maíz rellenas de ternera sazonada, salsa roja y crema.',                      'price' => 12.99, 'image_url' => 'https://images.unsplash.com/photo-1551504734-5ee1c4a1479b?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'mexican',  'name' => 'Chicken Burrito',                 'name_fr' => 'Burrito au Poulet',                    'name_es' => 'Burrito de Pollo',                     'slug' => 'chicken-burrito',               'description' => 'A loaded burrito with grilled chicken, rice, beans, cheese, and guacamole.',      'description_fr' => 'Burrito généreux avec poulet grillé, riz, haricots, fromage et guacamole.',          'description_es' => 'Un burrito cargado con pollo a la parrilla, arroz, frijoles, queso y guacamole.',          'price' => 13.49, 'image_url' => 'https://images.unsplash.com/photo-1626700051175-6818013e1d4f?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'mexican',  'name' => 'Birria Quesadilla',               'name_fr' => 'Quesadilla Birria',                    'name_es' => 'Quesadilla de Birria',                 'slug' => 'birria-quesadilla',             'description' => 'Cheesy griddled tortilla stuffed with juicy birria beef and onions.',             'description_fr' => 'Tortilla grillée et fromagée farcie de birria de bœuf juteux et oignons.',           'description_es' => 'Tortilla a la plancha y con queso rellena de jugosa birria de ternera y cebollas.',        'price' => 14.99, 'image_url' => 'https://images.unsplash.com/photo-1613514785940-daed07799d9b?w=400&q=70', 'availability' => 'seasonal'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'mexican',  'name' => 'Churros',                         'name_fr' => 'Churros',                              'name_es' => 'Churros',                              'slug' => 'churros',                       'description' => 'Warm cinnamon sugar churros served with chocolate dipping sauce.',                 'description_fr' => 'Churros chauds au sucre cannelle avec sauce au chocolat pour tremper.',              'description_es' => 'Churros calientes de azúcar y canela servidos con salsa de chocolate para mojar.',        'price' => 6.49,  'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Churros%20%284519186101%29.jpg', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'mexican',  'name' => 'Horchata',                        'name_fr' => 'Horchata',                             'name_es' => 'Horchata',                             'slug' => 'horchata',                      'description' => 'Sweet rice drink with cinnamon, vanilla, and a creamy finish.',                   'description_fr' => 'Boisson sucrée au riz avec cannelle, vanille et une touche crémeuse.',               'description_es' => 'Bebida dulce de arroz con canela, vainilla y un acabado cremoso.',                         'price' => 3.99,  'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Vaso%20de%20horchata.png', 'availability' => 'available'],
        // Italian
        ['category_name' => 'Food',     'cuisine_slug' => 'italian',  'name' => 'Margherita Pizza',                'name_fr' => 'Pizza Margherita',                     'name_es' => 'Pizza Margherita',                     'slug' => 'margherita-pizza',              'description' => 'Wood-fired pizza with tomato sauce, mozzarella, basil, and olive oil.',           'description_fr' => 'Pizza au feu de bois avec sauce tomate, mozzarella, basilic et huile d\'olive.',     'description_es' => 'Pizza al horno de leña con salsa de tomate, mozzarella, albahaca y aceite de oliva.',      'price' => 11.99, 'image_url' => 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'italian',  'name' => 'Spaghetti Carbonara',             'name_fr' => 'Spaghetti Carbonara',                  'name_es' => 'Espagueti Carbonara',                  'slug' => 'spaghetti-carbonara',           'description' => 'Silky spaghetti finished with pancetta, egg yolk, parmesan, and black pepper.',   'description_fr' => 'Spaghetti soyeux garni de pancetta, jaune d\'œuf, parmesan et poivre noir.',         'description_es' => 'Espagueti sedoso acabado con panceta, yema de huevo, parmesano y pimienta negra.',         'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'italian',  'name' => 'Truffle Mushroom Risotto',        'name_fr' => 'Risotto aux Champignons et Truffe',    'name_es' => 'Risotto de Setas y Trufa',             'slug' => 'truffle-mushroom-risotto',      'description' => 'Creamy arborio rice with roasted mushrooms, parmesan, and truffle aroma.',        'description_fr' => 'Riz arborio crémeux avec champignons rôtis, parmesan et arôme de truffe.',           'description_es' => 'Arroz arborio cremoso con champiñones asados, parmesano y aroma de trufa.',                'price' => 16.49, 'image_url' => 'https://images.unsplash.com/photo-1473093295043-cdd812d0e601?w=400&q=70', 'availability' => 'seasonal'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'italian',  'name' => 'Tiramisu',                        'name_fr' => 'Tiramisu',                             'name_es' => 'Tiramisú',                             'slug' => 'tiramisu',                      'description' => 'Espresso-soaked layers with mascarpone cream and cocoa.',                          'description_fr' => 'Couches trempées au café avec crème mascarpone et cacao.',                           'description_es' => 'Capas empapadas en espresso con crema de mascarpone y cacao.',                             'price' => 7.99,  'image_url' => 'https://images.unsplash.com/photo-1639744211487-b27e3551b07c?q=80&w=870&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'italian',  'name' => 'Sparkling Blood Orange Soda',     'name_fr' => 'Soda Pétillant à l\'Orange Sanguine',  'name_es' => 'Refresco de Naranja Sanguina con Gas', 'slug' => 'sparkling-blood-orange-soda',   'description' => 'Citrusy Italian soda with bright blood orange flavor and bubbles.',                'description_fr' => 'Soda italien aux agrumes avec saveur d\'orange sanguine et bulles.',                  'description_es' => 'Refresco italiano cítrico con sabor a naranja sanguina y burbujas.',                       'price' => 4.99,  'image_url' => 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=400&q=70', 'availability' => 'available'],
        // Indian
        ['category_name' => 'Food',     'cuisine_slug' => 'indian',   'name' => 'Chicken Biryani',                 'name_fr' => 'Biryani au Poulet',                    'name_es' => 'Biryani de Pollo',                     'slug' => 'chicken-biryani',               'description' => 'Fragrant basmati rice layered with spiced chicken, saffron, and herbs.',          'description_fr' => 'Riz basmati parfumé en couches avec poulet épicé, safran et herbes.',                'description_es' => 'Arroz basmati fragante en capas con pollo especiado, azafrán y hierbas.',                  'price' => 14.99, 'image_url' => 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'indian',   'name' => 'Butter Chicken',                  'name_fr' => 'Poulet au Beurre',                     'name_es' => 'Pollo con Mantequilla',                'slug' => 'butter-chicken',                'description' => 'Tender chicken simmered in a creamy tomato-butter curry sauce.',                  'description_fr' => 'Poulet tendre mijoté dans une sauce crémeuse à la tomate et au beurre.',             'description_es' => 'Pollo tierno en una cremosa salsa de tomate y mantequilla.',                               'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1588166524941-3bf61a9c41db?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'indian',   'name' => 'Paneer Tikka Masala',             'name_fr' => 'Tikka Masala au Paneer',               'name_es' => 'Tikka Masala de Paneer',               'slug' => 'paneer-tikka-masala',           'description' => 'Charred paneer cubes in a tomato-onion masala with warming spices.',              'description_fr' => 'Fromage paneer grillé dans une masala tomate-oignon avec épices chaleureuses.',      'description_es' => 'Queso paneer asado en un masala de tomate y cebolla con especias cálidas.',                'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'indian',   'name' => 'Gulab Jamun',                     'name_fr' => 'Gulab Jamun',                          'name_es' => 'Gulab Jamun',                          'slug' => 'gulab-jamun',                   'description' => 'Soft fried dumplings soaked in cardamom and rose syrup.',                         'description_fr' => 'Boulettes frites tendres imbibées de sirop à la cardamome et à la rose.',            'description_es' => 'Suaves bolas fritas empapadas en jarabe de cardamomo y rosa.',                             'price' => 5.99,  'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Gulab%20Jamuns.jpg', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'indian',   'name' => 'Mango Lassi',                     'name_fr' => 'Lassi à la Mangue',                    'name_es' => 'Lassi de Mango',                       'slug' => 'mango-lassi',                   'description' => 'Chilled mango yogurt drink with a silky, refreshing finish.',                     'description_fr' => 'Boisson froide au yaourt et à la mangue avec une fin soyeuse et rafraîchissante.',   'description_es' => 'Bebida fría de yogur y mango con un acabado suave y refrescante.',                         'price' => 4.99,  'image_url' => 'https://images.unsplash.com/photo-1623065422902-30a2d299bbe4?q=80&w=774&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        // Lebanese
        ['category_name' => 'Food',     'cuisine_slug' => 'lebanese', 'name' => 'Chicken Shawarma',                'name_fr' => 'Chawarma au Poulet',                   'name_es' => 'Shawarma de Pollo',                    'slug' => 'chicken-shawarma',              'description' => 'Juicy marinated chicken wrapped with garlic sauce, pickles, and lettuce.',        'description_fr' => 'Poulet mariné juteux enveloppé avec sauce à l\'ail, cornichons et laitue.',           'description_es' => 'Pollo marinado jugoso envuelto con salsa de ajo, encurtidos y lechuga.',                   'price' => 12.49, 'image_url' => 'https://images.unsplash.com/photo-1676300187013-7540d4e9440d?q=80&w=1740&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'lebanese', 'name' => 'Falafel Plate',                   'name_fr' => 'Assiette de Falafel',                  'name_es' => 'Plato de Falafel',                     'slug' => 'falafel-plate',                 'description' => 'Crispy falafel with hummus, tabbouleh, pickles, and warm pita.',                  'description_fr' => 'Falafel croustillant avec houmous, taboulé, cornichons et pita chaud.',              'description_es' => 'Falafel crujiente con hummus, tabulé, encurtidos y pita caliente.',                        'price' => 11.99, 'image_url' => 'https://images.unsplash.com/photo-1593001872095-7d5b3868fb1d?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'lebanese', 'name' => 'Kafta Skewers',                   'name_fr' => 'Brochettes de Kafta',                  'name_es' => 'Brochetas de Kafta',                   'slug' => 'kafta-skewers',                 'description' => 'Chargrilled beef kafta with rice, grilled vegetables, and tahini.',               'description_fr' => 'Kafta de bœuf grillée avec riz, légumes grillés et tahini.',                         'description_es' => 'Kafta de ternera a la parrilla con arroz, verduras asadas y tahini.',                      'price' => 15.49, 'image_url' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=400&q=70', 'availability' => 'seasonal'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'lebanese', 'name' => 'Baklava',                         'name_fr' => 'Baklava',                              'name_es' => 'Baklava',                              'slug' => 'baklava',                       'description' => 'Flaky pastry layers filled with pistachios and honey syrup.',                     'description_fr' => 'Couches de pâte feuilletée garnies de pistaches et sirop de miel.',                  'description_es' => 'Capas de pasta hojaldrada rellenas de pistachos y jarabe de miel.',                        'price' => 6.49,  'image_url' => 'https://images.unsplash.com/photo-1519676867240-f03562e64548?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'lebanese', 'name' => 'Mint Lemonade',                   'name_fr' => 'Limonade à la Menthe',                 'name_es' => 'Limonada de Menta',                    'slug' => 'mint-lemonade',                 'description' => 'Fresh lemon juice blended with mint leaves and crushed ice.',                     'description_fr' => 'Jus de citron frais mixé avec des feuilles de menthe et de la glace pilée.',         'description_es' => 'Zumo de limón fresco mezclado con hojas de menta y hielo picado.',                        'price' => 4.49,  'image_url' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=400&q=70', 'availability' => 'available'],
        // American
        ['category_name' => 'Food',     'cuisine_slug' => 'american', 'name' => 'Classic Cheeseburger',            'name_fr' => 'Cheeseburger Classique',               'name_es' => 'Hamburguesa Clásica con Queso',        'slug' => 'classic-cheeseburger',          'description' => 'Grilled beef patty with cheddar, pickles, lettuce, tomato, and sauce.',          'description_fr' => 'Steak haché grillé avec cheddar, cornichons, laitue, tomate et sauce.',              'description_es' => 'Hamburguesa de ternera a la parrilla con cheddar, pepinillos, lechuga, tomate y salsa.',   'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'american', 'name' => 'BBQ Ribs',                        'name_fr' => 'Côtes BBQ',                            'name_es' => 'Costillas BBQ',                        'slug' => 'bbq-ribs',                      'description' => 'Slow-cooked ribs glazed with smoky barbecue sauce and charred edges.',            'description_fr' => 'Côtes cuites lentement, glacées à la sauce barbecue fumée avec bords carbonisés.',  'description_es' => 'Costillas cocinadas a fuego lento con glaseado de salsa barbacoa ahumada y bordes tostados.', 'price' => 18.99, 'image_url' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'american', 'name' => 'Nashville Hot Chicken Sandwich',  'name_fr' => 'Sandwich Poulet Piquant Nashville',    'name_es' => 'Sándwich de Pollo Picante Nashville',  'slug' => 'nashville-hot-chicken-sandwich', 'description' => 'Crispy spicy chicken sandwich with slaw, pickles, and toasted brioche.',          'description_fr' => 'Sandwich au poulet croustillant et épicé avec salade, cornichons et brioche.',        'description_es' => 'Sándwich de pollo crujiente y picante con ensalada, pepinillos y brioche tostado.',        'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'american', 'name' => 'New York Cheesecake',             'name_fr' => 'Cheesecake New-Yorkais',               'name_es' => 'Tarta de Queso de Nueva York',         'slug' => 'new-york-cheesecake',           'description' => 'Dense creamy cheesecake finished with a buttery graham crust.',                   'description_fr' => 'Cheesecake dense et crémeux avec une croûte de graham beurrée.',                     'description_es' => 'Tarta de queso densa y cremosa con una base de galleta graham mantecada.',                 'price' => 7.49,  'image_url' => 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'american', 'name' => 'Vanilla Cream Soda',              'name_fr' => 'Soda Crème Vanille',                   'name_es' => 'Refresco de Vainilla y Nata',          'slug' => 'vanilla-cream-soda',            'description' => 'Old-school cream soda with vanilla sweetness and fizzy lift.',                    'description_fr' => 'Soda old-school à la crème avec douceur vanillée et légèreté pétillante.',           'description_es' => 'Refresco clásico de crema con dulzura de vainilla y efervescencia ligera.',                'price' => 3.99,  'image_url' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=400&q=70', 'availability' => 'unavailable'],
        // Thai
        ['category_name' => 'Food',     'cuisine_slug' => 'thai',     'name' => 'Pad Thai',                        'name_fr' => 'Pad Thaï',                             'name_es' => 'Pad Thai',                             'slug' => 'pad-thai',                      'description' => 'Rice noodles stir-fried with tamarind, peanuts, and lime.',                       'description_fr' => 'Nouilles de riz sautées avec tamarin, cacahuètes et citron vert.',                   'description_es' => 'Fideos de arroz salteados con tamarindo, cacahuetes y lima.',                              'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'thai',     'name' => 'Green Curry',                     'name_fr' => 'Curry Vert',                           'name_es' => 'Curry Verde',                          'slug' => 'green-curry',                   'description' => 'Creamy coconut curry with chicken, Thai basil, and tender vegetables.',           'description_fr' => 'Curry à la noix de coco avec poulet, basilic thaï et légumes tendres.',              'description_es' => 'Curry cremoso de coco con pollo, albahaca tailandesa y verduras tiernas.',                 'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1455619452474-d2be8b1e70cd?w=400&q=70', 'availability' => 'available'],
        ['category_name' => 'Food',     'cuisine_slug' => 'thai',     'name' => 'Thai Basil Beef',                 'name_fr' => 'Bœuf au Basilic Thaï',                 'name_es' => 'Ternera con Albahaca Tailandesa',       'slug' => 'thai-basil-beef',               'description' => 'Savory beef stir-fry with chilies, garlic, basil, and jasmine rice.',             'description_fr' => 'Sauté de bœuf savoureux aux piments, ail, basilic et riz au jasmin.',                'description_es' => 'Saltado de ternera sabroso con chiles, ajo, albahaca y arroz jazmín.',                     'price' => 15.29, 'image_url' => 'https://images.unsplash.com/photo-1569562211093-4ed0d0758f12?w=400&q=70', 'availability' => 'seasonal'],
        ['category_name' => 'Desserts', 'cuisine_slug' => 'thai',     'name' => 'Mango Sticky Rice',               'name_fr' => 'Riz Gluant à la Mangue',               'name_es' => 'Arroz Glutinoso con Mango',             'slug' => 'mango-sticky-rice',             'description' => 'Sweet glutinous rice topped with ripe mango and coconut cream.',                  'description_fr' => 'Riz glutineux sucré garni de mangue mûre et de crème de coco.',                     'description_es' => 'Arroz glutinoso dulce cubierto de mango maduro y crema de coco.',                         'price' => 6.99,  'image_url' => 'https://images.unsplash.com/photo-1711161988375-da7eff032e45?q=80&w=1740&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_name' => 'Drinks',   'cuisine_slug' => 'thai',     'name' => 'Thai Iced Tea',                   'name_fr' => 'Thé Glacé Thaï',                       'name_es' => 'Té Helado Tailandés',                  'slug' => 'thai-iced-tea',                 'description' => 'Sweet spiced black tea with condensed milk poured over ice.',                     'description_fr' => 'Thé noir doux et épicé avec lait concentré sucré versé sur de la glace.',            'description_es' => 'Té negro dulce y especiado con leche condensada vertida sobre hielo.',                     'price' => 4.79,  'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Thai%20iced%20tea.jpg', 'availability' => 'available'],
    ];

    $stmt = $pdo->prepare('INSERT INTO dishes (category_id, cuisine_id, name, name_fr, name_es, slug, description, description_fr, description_es, price, image_url, availability) VALUES (:category_id, :cuisine_id, :name, :name_fr, :name_es, :slug, :description, :description_fr, :description_es, :price, :image_url, :availability)');
    $dishPrices = [];
    foreach ($dishes as $row) {
        $stmt->execute([
            'category_id'    => $categoryIds[$row['category_name']],
            'cuisine_id'     => $cuisineIds[$row['cuisine_slug']],
            'name'           => $row['name'],
            'name_fr'        => $row['name_fr'],
            'name_es'        => $row['name_es'],
            'slug'           => $row['slug'],
            'description'    => $row['description'],
            'description_fr' => $row['description_fr'],
            'description_es' => $row['description_es'],
            'price'          => $row['price'],
            'image_url'      => $row['image_url'],
            'availability'   => $row['availability'],
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
