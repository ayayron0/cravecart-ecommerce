<?php

/**
 * seed.php — Database seeder
 *
 * WHAT: Creates all tables and fills them with sample data.
 * HOW:  Run this once from the terminal: php data/seed.php
 *
 * IMPORTANT: Table names are lowercase and primary keys are named "id"
 *            so they match what RedBeanPHP expects by default.
 */

require_once __DIR__ . '/../vendor/autoload.php';

define('APP_ROOT_DIR_NAME', 'cravecart-ecommerce');
define('APP_BASE_DIR_PATH', __DIR__ . '/..');

// Load DB credentials directly from env.php (plain array)
$db = require __DIR__ . '/../config/env.php';

try {
    // Connect without a database first so we can create it if it doesn't exist
    $pdo_no_db = new PDO(
        sprintf('mysql:host=%s;charset=utf8mb4', $db['host']),
        $db['username'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo_no_db->exec("CREATE DATABASE IF NOT EXISTS `{$db['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database '{$db['database']}' ready.\n";

    // Connect to the database
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['database']),
        $db['username'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "✅ Connected to database.\n\n";

    // -------------------------------------------------------------------------
    // RESET SCHEMA
    // The seeder is the source of truth for this classroom project, so we drop
    // and recreate the tables on each run to ensure schema changes (like new
    // foreign key rules) are applied consistently.
    // -------------------------------------------------------------------------
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach (['order_dish','orders','saved_cart','delivery_address','dishes','categories','cuisines','users'] as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "✅ Reset existing schema.\n";

    // -------------------------------------------------------------------------
    // CREATE TABLES
    // Note: all table names are lowercase, all primary keys are named "id"
    //       This matches RedBeanPHP's default conventions.
    // -------------------------------------------------------------------------

    // users
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
    echo "✅ Table: users\n";

    // cuisines — code (CN, JP...), slug (chinese, japanese...) and image_url
    // are needed by the home page to display cuisine cards from the database
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
    echo "✅ Table: cuisines\n";

    // categories — Food / Desserts / Drinks
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255)
        )
    ");
    echo "✅ Table: categories\n";

    // dishes — linked to both a category (Food/Desserts/Drinks)
    //          and a cuisine (Chinese/Japanese...) via foreign keys
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dishes (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            category_id  INT NOT NULL,
            cuisine_id   INT NOT NULL,
            name         VARCHAR(150) NOT NULL,
            description  VARCHAR(500),
            price        DECIMAL(10,2) NOT NULL,
            image_url    VARCHAR(500),
            availability VARCHAR(20) NOT NULL DEFAULT 'available',
            CONSTRAINT fk_dishes_categories FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
            CONSTRAINT fk_dishes_cuisines   FOREIGN KEY (cuisine_id)  REFERENCES cuisines(id) ON DELETE CASCADE
        )
    ");
    echo "✅ Table: dishes\n";

    // delivery_address — linked to a user
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
    echo "✅ Table: delivery_address\n";

    // orders — linked to a user and a delivery address
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
    echo "✅ Table: orders\n";

    // order_dish — links orders to dishes (many-to-many)
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
    echo "✅ Table: order_dish\n";

    // saved_cart — a user's cart items linked to dishes
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
    echo "✅ Table: saved_cart\n";

    echo "\n✅ All tables created.\n\n";

    // -------------------------------------------------------------------------
    // SEED: users
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)");
    foreach ([
        ['name' => 'Admin User',   'email' => 'admin@cravecart.com', 'password_hash' => password_hash('admin1234',   PASSWORD_BCRYPT), 'role' => 'administrator'],
        ['name' => 'Alice Johnson', 'email' => 'alice@example.com',   'password_hash' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'client'],
        ['name' => 'Bob Smith',     'email' => 'bob@example.com',     'password_hash' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'client'],
        ['name' => 'Carol White',   'email' => 'carol@example.com',   'password_hash' => password_hash('password123', PASSWORD_BCRYPT), 'role' => 'client'],
    ] as $row) { $stmt->execute($row); }
    echo "✅ Seeded users.\n";

    // -------------------------------------------------------------------------
    // SEED: cuisines
    // image_url matches the Unsplash photos used in home.twig
    // code is the 2-letter country code shown on the cuisine card
    // slug is used in the URL: /browse/food/chinese
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("INSERT INTO cuisines (name, code, slug, description, image_url) VALUES (:name, :code, :slug, :description, :image_url)");
    foreach ([
        ['name' => 'Chinese',  'code' => 'CN', 'slug' => 'chinese',  'description' => 'Bold flavours with noodles, rice, and dim sum',          'image_url' => 'https://images.unsplash.com/photo-1563245372-f21724e3856d?w=400&q=70'],
        ['name' => 'Japanese', 'code' => 'JP', 'slug' => 'japanese', 'description' => 'Fresh and delicate sushi, ramen, and yakitori',           'image_url' => 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=400&q=70'],
        ['name' => 'Mexican',  'code' => 'MX', 'slug' => 'mexican',  'description' => 'Vibrant tacos, burritos, and spicy salsas',               'image_url' => 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?w=400&q=70'],
        ['name' => 'Italian',  'code' => 'IT', 'slug' => 'italian',  'description' => 'Classic pizzas, pastas, and Mediterranean comfort food',  'image_url' => 'https://images.unsplash.com/photo-1555949258-eb67b1ef0ceb?w=400&q=70'],
        ['name' => 'Indian',   'code' => 'IN', 'slug' => 'indian',   'description' => 'Rich curries, biryanis, and aromatic spiced dishes',      'image_url' => 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=400&q=70'],
        ['name' => 'Lebanese', 'code' => 'LB', 'slug' => 'lebanese', 'description' => 'Fresh mezze, shawarma, and grilled meats',                'image_url' => 'https://images.unsplash.com/photo-1541518763669-27fef04b14ea?w=400&q=70'],
        ['name' => 'American', 'code' => 'US', 'slug' => 'american', 'description' => 'Juicy burgers, BBQ ribs, and classic comfort food',       'image_url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400&q=70'],
        ['name' => 'Thai',     'code' => 'TH', 'slug' => 'thai',     'description' => 'Fragrant pad thai, green curry, and mango sticky rice',   'image_url' => 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=400&q=70'],
    ] as $row) { $stmt->execute($row); }
    echo "✅ Seeded cuisines.\n";

    // -------------------------------------------------------------------------
    // SEED: categories  (id: 1=Food, 2=Desserts, 3=Drinks)
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
    foreach ([
        ['name' => 'Food',     'description' => 'Main dishes from various cuisines'],
        ['name' => 'Desserts', 'description' => 'Sweet treats and baked goods'],
        ['name' => 'Drinks',   'description' => 'Cold and hot beverages'],
    ] as $row) { $stmt->execute($row); }
    echo "✅ Seeded categories.\n";

    // -------------------------------------------------------------------------
    // SEED: dishes
    // Each dish is linked to a category (Food/Desserts/Drinks)
    // AND a cuisine (Chinese/Japanese/etc.) via foreign keys
    // cuisine ids: 1=Chinese 2=Japanese 3=Mexican 4=Italian 5=Indian 6=Lebanese 7=American 8=Thai
    // category ids: 1=Food 2=Desserts 3=Drinks
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("INSERT INTO dishes (category_id, cuisine_id, name, description, price, image_url, availability) VALUES (:category_id, :cuisine_id, :name, :description, :price, :image_url, :availability)");
    foreach ([
        // Chinese food
        ['category_id' => 1, 'cuisine_id' => 1, 'name' => 'Kung Pao Chicken',    'description' => 'Spicy stir-fried chicken with peanuts and chili peppers',        'price' => 13.99, 'image_url' => 'https://www.chilipeppermadness.com/wp-content/uploads/2021/03/Kung-Pao-Chicken-Recipe3a.jpg', 'availability' => 'available'],
        ['category_id' => 1, 'cuisine_id' => 1, 'name' => 'Beef Fried Rice',      'description' => 'Wok-fried rice with beef, egg, and vegetables',                  'price' => 11.99, 'image_url' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=400&q=70', 'availability' => 'available'],
        // Japanese food
        ['category_id' => 1, 'cuisine_id' => 2, 'name' => 'Salmon Roll (8 pcs)', 'description' => 'Fresh salmon with cucumber and avocado',                          'price' => 15.99, 'image_url' => 'https://plus.unsplash.com/premium_photo-1668143360914-d8905f8b2bd1?q=80&w=774&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_id' => 1, 'cuisine_id' => 2, 'name' => 'Chicken Ramen',        'description' => 'Rich broth with noodles, soft egg, and grilled chicken',          'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1680593180878-e0cd1e99486e?q=80&w=1160&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        // Mexican food
        ['category_id' => 1, 'cuisine_id' => 3, 'name' => 'Beef Tacos (3 pcs)',  'description' => 'Corn tortillas with seasoned beef, salsa, and sour cream',        'price' => 12.99, 'image_url' => 'https://images.unsplash.com/photo-1551504734-5ee1c4a1479b?w=400&q=70', 'availability' => 'available'],
        ['category_id' => 1, 'cuisine_id' => 3, 'name' => 'Chicken Burrito',      'description' => 'Flour tortilla loaded with chicken, rice, beans, and guacamole', 'price' => 13.49, 'image_url' => 'https://images.unsplash.com/photo-1626700051175-6818013e1d4f?w=400&q=70', 'availability' => 'available'],
        // Italian food
        ['category_id' => 1, 'cuisine_id' => 4, 'name' => 'Margherita Pizza',    'description' => 'Classic tomato sauce, mozzarella, and fresh basil',               'price' => 11.99, 'image_url' => 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=400&q=70', 'availability' => 'available'],
        ['category_id' => 1, 'cuisine_id' => 4, 'name' => 'Spaghetti Carbonara', 'description' => 'Creamy egg sauce with pancetta and parmesan',                      'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=400&q=70', 'availability' => 'available'],
        // Indian food
        ['category_id' => 1, 'cuisine_id' => 5, 'name' => 'Chicken Biryani',     'description' => 'Fragrant basmati rice with spiced chicken and caramelized onions','price' => 14.99, 'image_url' => 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=400&q=70', 'availability' => 'available'],
        ['category_id' => 1, 'cuisine_id' => 5, 'name' => 'Butter Chicken',       'description' => 'Tender chicken in a creamy tomato-based curry sauce',             'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1588166524941-3bf61a9c41db?w=400&q=70', 'availability' => 'available'],
        // Lebanese food
        ['category_id' => 1, 'cuisine_id' => 6, 'name' => 'Chicken Shawarma',    'description' => 'Marinated chicken in pita with garlic sauce and pickles',         'price' => 12.49, 'image_url' => 'https://images.unsplash.com/photo-1676300187013-7540d4e9440d?q=80&w=1740&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_id' => 1, 'cuisine_id' => 6, 'name' => 'Falafel Plate',        'description' => 'Crispy falafel with hummus, tabbouleh, and pita bread',           'price' => 11.99, 'image_url' => 'https://images.unsplash.com/photo-1593001872095-7d5b3868fb1d?w=400&q=70', 'availability' => 'available'],
        // American food
        ['category_id' => 1, 'cuisine_id' => 7, 'name' => 'Classic Cheeseburger','description' => 'Beef patty with cheddar, lettuce, tomato, and pickles',           'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400&q=70', 'availability' => 'available'],
        ['category_id' => 1, 'cuisine_id' => 7, 'name' => 'BBQ Ribs',             'description' => 'Slow-cooked pork ribs glazed with smoky BBQ sauce',               'price' => 18.99, 'image_url' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=400&q=70', 'availability' => 'available'],
        // Thai food
        ['category_id' => 1, 'cuisine_id' => 8, 'name' => 'Pad Thai',            'description' => 'Stir-fried rice noodles with shrimp, peanuts, and lime',          'price' => 13.99, 'image_url' => 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=400&q=70', 'availability' => 'available'],
        ['category_id' => 1, 'cuisine_id' => 8, 'name' => 'Green Curry',          'description' => 'Creamy coconut curry with chicken, eggplant, and Thai basil',     'price' => 14.49, 'image_url' => 'https://images.unsplash.com/photo-1455619452474-d2be8b1e70cd?w=400&q=70', 'availability' => 'available'],
        // Desserts
        ['category_id' => 2, 'cuisine_id' => 4, 'name' => 'Tiramisu',            'description' => 'Classic Italian dessert with espresso-soaked ladyfingers',        'price' => 7.99,  'image_url' => 'https://images.unsplash.com/photo-1639744211487-b27e3551b07c?q=80&w=870&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_id' => 2, 'cuisine_id' => 2, 'name' => 'Mochi Ice Cream',     'description' => 'Japanese rice cake filled with creamy ice cream',                  'price' => 6.49,  'image_url' => 'https://plus.unsplash.com/premium_photo-1701104846200-9cbfccc8a457?q=80&w=774&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_id' => 2, 'cuisine_id' => 5, 'name' => 'Gulab Jamun',         'description' => 'Soft milk-solid dumplings soaked in rose-flavored sugar syrup',   'price' => 5.99,  'image_url' => 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=400&q=70', 'availability' => 'available'],
        ['category_id' => 2, 'cuisine_id' => 8, 'name' => 'Mango Sticky Rice',   'description' => 'Sweet glutinous rice with fresh mango and coconut milk',          'price' => 6.99,  'image_url' => 'https://images.unsplash.com/photo-1711161988375-da7eff032e45?q=80&w=1740&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        // Drinks
        ['category_id' => 3, 'cuisine_id' => 5, 'name' => 'Mango Lassi',         'description' => 'Chilled yogurt-based mango drink with a hint of cardamom',        'price' => 4.99,  'image_url' => 'https://images.unsplash.com/photo-1623065422902-30a2d299bbe4?q=80&w=774&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_id' => 3, 'cuisine_id' => 2, 'name' => 'Matcha Latte',        'description' => 'Japanese green tea powder with steamed milk',                      'price' => 5.49,  'image_url' => 'https://images.unsplash.com/photo-1515823064-d6e0c04616a7?q=80&w=1742&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 'availability' => 'available'],
        ['category_id' => 3, 'cuisine_id' => 4, 'name' => 'Café au Lait',        'description' => 'French-style coffee with equal parts brewed coffee and steamed milk','price' => 4.49,'image_url' => 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=400&q=70', 'availability' => 'available'],
        ['category_id' => 3, 'cuisine_id' => 3, 'name' => 'Horchata',            'description' => 'Sweet Mexican rice drink with cinnamon and vanilla',               'price' => 3.99,  'image_url' => 'https://www.yummymummykitchen.com/wp-content/uploads/2018/04/how-to-make-horchata-4-725x1088.jpg', 'availability' => 'available'],
    ] as $row) { $stmt->execute($row); }
    echo "✅ Seeded dishes.\n";

    // -------------------------------------------------------------------------
    // SEED: delivery_address
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("INSERT INTO delivery_address (user_id, street, city, postal_code) VALUES (:user_id, :street, :city, :postal_code)");
    foreach ([
        ['user_id' => 2, 'street' => '123 Maple Street',   'city' => 'Montreal', 'postal_code' => 'H2X 1Y4'],
        ['user_id' => 3, 'street' => '456 Oak Avenue',     'city' => 'Gatineau', 'postal_code' => 'J8T 3R2'],
        ['user_id' => 4, 'street' => '789 Pine Boulevard', 'city' => 'Ottawa',   'postal_code' => 'K1A 0A6'],
    ] as $row) { $stmt->execute($row); }
    echo "✅ Seeded delivery_address.\n";

    // -------------------------------------------------------------------------
    // SEED: orders
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, address_id, subtotal, taxes, total, status, notes) VALUES (:user_id, :address_id, :subtotal, :taxes, :total, :status, :notes)");
    foreach ([
        ['user_id' => 2, 'address_id' => 1, 'subtotal' => 27.98, 'taxes' => 3.64, 'total' => 31.62, 'status' => 'delivered',    'notes' => 'Leave at the door'],
        ['user_id' => 3, 'address_id' => 2, 'subtotal' => 30.48, 'taxes' => 3.96, 'total' => 34.44, 'status' => 'in progress',  'notes' => null],
        ['user_id' => 4, 'address_id' => 3, 'subtotal' => 13.99, 'taxes' => 1.82, 'total' => 15.81, 'status' => 'pending',      'notes' => 'Extra napkins please'],
    ] as $row) { $stmt->execute($row); }
    echo "✅ Seeded orders.\n";

    // -------------------------------------------------------------------------
    // SEED: order_dish
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("INSERT INTO order_dish (order_id, dish_id, quantity, item_price) VALUES (:order_id, :dish_id, :quantity, :item_price)");
    foreach ([
        ['order_id' => 1, 'dish_id' => 9,  'quantity' => 1, 'item_price' => 14.99], // Chicken Biryani
        ['order_id' => 1, 'dish_id' => 7,  'quantity' => 1, 'item_price' => 11.99], // Margherita Pizza
        ['order_id' => 2, 'dish_id' => 3,  'quantity' => 1, 'item_price' => 15.99], // Salmon Roll
        ['order_id' => 2, 'dish_id' => 14, 'quantity' => 1, 'item_price' => 18.99], // BBQ Ribs
        ['order_id' => 3, 'dish_id' => 7,  'quantity' => 1, 'item_price' => 13.99], // Margherita Pizza
    ] as $row) { $stmt->execute($row); }
    echo "✅ Seeded order_dish.\n";

    echo "\n🎉 Database seeded successfully!\n";
    echo "────────────────────────────────────\n";
    echo "Admin:  admin@cravecart.com / admin1234\n";
    echo "Client: alice@example.com   / password123\n";
    echo "Client: bob@example.com     / password123\n";
    echo "Client: carol@example.com   / password123\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
