<?php
/**
 * CraveCart Database Seeder
 * 
 * This script creates all necessary tables using the provided schema
 * and seeds the database with sample data.
 * Usage: php data/seed.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

define('APP_ROOT_DIR_NAME', 'cravecart-ecommerce');
define('APP_BASE_DIR_PATH', __DIR__ . '/..');

// Load the application settings
$settings = require __DIR__ . '/../config/settings.php';

try {
    $db_config = $settings['db'];
    
    // First, connect without specifying database to create it if needed
    $pdo_no_db = new PDO(
        'mysql:host=' . $db_config['host'] . ';charset=utf8mb4',
        $db_config['username'],
        $db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create database if it doesn't exist
    try {
        $pdo_no_db->exec("CREATE DATABASE IF NOT EXISTS `" . $db_config['database'] . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✅ Database '" . $db_config['database'] . "' created or already exists.\n";
    } catch (PDOException $e) {
        echo "⚠️ Database creation skipped (may already exist): " . $e->getMessage() . "\n";
    }
    
    // Now connect to the specific database
    $pdo = new PDO(
        'mysql:host=' . $db_config['host'] . ';dbname=' . $db_config['database'] . ';charset=utf8mb4',
        $db_config['username'],
        $db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Connected to database.\n";

    // -------------------------------------------------------
    // Create tables using the provided schema
    // -------------------------------------------------------

    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'client' CHECK (role IN ('administrator', 'client')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Created Users table.\n";

    // Cuisines table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Cuisines (
            cuisine_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255)
        )
    ");
    echo "✅ Created Cuisines table.\n";

    // Categories table (with cuisine_id FK)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Categories (
            category_id INT AUTO_INCREMENT PRIMARY KEY,
            cuisine_id INT,
            name VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255),
            CONSTRAINT fk_Categories_Cuisines FOREIGN KEY (cuisine_id) REFERENCES Cuisines(cuisine_id) ON DELETE SET NULL
        )
    ");
    echo "✅ Created Categories table.\n";

    // Delivery_Address table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Delivery_Address (
            address_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            street VARCHAR(255) NOT NULL,
            city VARCHAR(100) NOT NULL,
            postal_code VARCHAR(20) NOT NULL,
            CONSTRAINT fk_DeliveryAddress_Users FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
        )
    ");
    echo "✅ Created Delivery_Address table.\n";

    // Orders table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Orders (
            order_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            address_id INT NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            taxes DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'in progress', 'out for delivery', 'delivered', 'cancelled')),
            notes VARCHAR(500),
            ordered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_Orders_Users FOREIGN KEY (user_id) REFERENCES Users(user_id),
            CONSTRAINT fk_Orders_DeliveryAddress FOREIGN KEY (address_id) REFERENCES Delivery_Address(address_id)
        )
    ");
    echo "✅ Created Orders table.\n";

    // Dishes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Dishes (
            dish_id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            description VARCHAR(500),
            price DECIMAL(10,2) NOT NULL,
            image_url VARCHAR(500),
            availability VARCHAR(20) NOT NULL DEFAULT 'available' CHECK (availability IN ('available', 'unavailable', 'seasonal', 'out of stock')),
            CONSTRAINT fk_Dishes_Categories FOREIGN KEY (category_id) REFERENCES Categories(category_id)
        )
    ");
    echo "✅ Created Dishes table.\n";

    // Order_Dish table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Order_Dish (
            order_dish_id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            dish_id INT NOT NULL,
            quantity INT NOT NULL,
            item_price DECIMAL(10,2) NOT NULL,
            CONSTRAINT fk_OrderDish_Orders FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,
            CONSTRAINT fk_OrderDish_Dishes FOREIGN KEY (dish_id) REFERENCES Dishes(dish_id)
        )
    ");
    echo "✅ Created Order_Dish table.\n";

    // Saved_Cart table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Saved_Cart (
            saved_cart_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            dish_id INT NOT NULL,
            quantity INT NOT NULL,
            dish_price DECIMAL(10,2) NOT NULL,
            saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_SavedCart_Users FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
            CONSTRAINT fk_SavedCart_Dishes FOREIGN KEY (dish_id) REFERENCES Dishes(dish_id)
        )
    ");
    echo "✅ Created Saved_Cart table.\n";

    echo "\n🎉 All tables created successfully!\n";

    // -------------------------------------------------------
    // Clear existing data (order matters due to foreign keys)
    // -------------------------------------------------------
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE Order_Dish");
    $pdo->exec("TRUNCATE TABLE Orders");
    $pdo->exec("TRUNCATE TABLE Saved_Cart");
    $pdo->exec("TRUNCATE TABLE Delivery_Address");
    $pdo->exec("TRUNCATE TABLE Dishes");
    $pdo->exec("TRUNCATE TABLE Categories");
    $pdo->exec("TRUNCATE TABLE Cuisines");
    $pdo->exec("TRUNCATE TABLE Users");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "Cleared existing data.\n";

    // -------------------------------------------------------
    // Seed data
    // -------------------------------------------------------

    // Users (1 admin + 3 clients)
    $users = [
        [
            'name'          => 'Admin User',
            'email'         => 'admin@cravecart.com',
            'password_hash' => password_hash('admin1234', PASSWORD_BCRYPT),
            'role'          => 'administrator',
        ],
        [
            'name'          => 'Alice Johnson',
            'email'         => 'alice@example.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'role'          => 'client',
        ],
        [
            'name'          => 'Bob Smith',
            'email'         => 'bob@example.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'role'          => 'client',
        ],
        [
            'name'          => 'Carol White',
            'email'         => 'carol@example.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'role'          => 'client',
        ],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO Users (name, email, password_hash, role)
        VALUES (:name, :email, :password_hash, :role)
    ");

    foreach ($users as $user) {
        $stmt->execute($user);
    }

    echo "Seeded Users.\n";

    // Cuisines
    // cuisine_id: 1=Pakistani, 2=Japanese, 3=Italian, 4=French
    $cuisines = [
        ['name' => 'Pakistani', 'description' => 'Rich and aromatic South Asian cuisine'],
        ['name' => 'Japanese',  'description' => 'Fresh and delicate East Asian cuisine'],
        ['name' => 'Italian',   'description' => 'Classic Mediterranean comfort food'],
        ['name' => 'French',    'description' => 'Refined and elegant European cuisine'],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO Cuisines (name, description)
        VALUES (:name, :description)
    ");

    foreach ($cuisines as $cuisine) {
        $stmt->execute($cuisine);
    }

    echo "Seeded Cuisines.\n";

    // Categories
    // category_id: 1=Food, 2=Desserts, 3=Drinks
    // cuisine_id is NULL as categories span all cuisines
    $categories = [
        ['cuisine_id' => null, 'name' => 'Food',     'description' => 'Main dishes from various cuisines'],
        ['cuisine_id' => null, 'name' => 'Desserts',  'description' => 'Sweet treats and cakes'],
        ['cuisine_id' => null, 'name' => 'Drinks',    'description' => 'Cold and hot beverages'],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO Categories (cuisine_id, name, description)
        VALUES (:cuisine_id, :name, :description)
    ");

    foreach ($categories as $category) {
        $stmt->execute($category);
    }

    echo "Seeded Categories.\n";

    // Dishes
    // All food dishes use category_id: 1 (Food)
    // Desserts use category_id: 2, Drinks use category_id: 3
    $dishes = [
        // Pakistani dishes (category_id: 1 - Food)
        ['category_id' => 1, 'name' => 'Chicken Biryani',        'description' => 'Fragrant basmati rice with spiced chicken and caramelized onions',    'price' => 14.99, 'image_url' => null, 'availability' => 'available'],
        ['category_id' => 1, 'name' => 'Beef Karahi',             'description' => 'Tender beef cooked in a wok with tomatoes, ginger, and fresh spices', 'price' => 16.99, 'image_url' => null, 'availability' => 'available'],
        ['category_id' => 1, 'name' => 'Chicken Tikka',           'description' => 'Marinated chicken grilled on skewers with mint chutney',              'price' => 13.49, 'image_url' => null, 'availability' => 'available'],

        // Japanese dishes (category_id: 1 - Food)
        ['category_id' => 1, 'name' => 'Salmon Roll (8 pcs)',     'description' => 'Fresh salmon with cucumber and avocado',                              'price' => 15.99, 'image_url' => null, 'availability' => 'available'],
        ['category_id' => 1, 'name' => 'Spicy Tuna Roll (8 pcs)', 'description' => 'Spicy tuna mix with sesame seeds',                                   'price' => 14.99, 'image_url' => null, 'availability' => 'available'],
        ['category_id' => 1, 'name' => 'Dragon Roll (8 pcs)',     'description' => 'Shrimp tempura topped with avocado and eel sauce',                   'price' => 17.99, 'image_url' => null, 'availability' => 'seasonal'],

        // Italian dishes (category_id: 1 - Food)
        ['category_id' => 1, 'name' => 'Margherita Pizza',        'description' => 'Classic tomato sauce, mozzarella, and fresh basil',                  'price' => 11.99, 'image_url' => null, 'availability' => 'available'],
        ['category_id' => 1, 'name' => 'Pepperoni Pizza',         'description' => 'Loaded with pepperoni and mozzarella cheese',                        'price' => 13.99, 'image_url' => null, 'availability' => 'available'],
        ['category_id' => 1, 'name' => 'Spaghetti Carbonara',     'description' => 'Creamy egg sauce with pancetta and parmesan',                        'price' => 13.99, 'image_url' => null, 'availability' => 'available'],

        // French dishes (category_id: 1 - Food)
        ['category_id' => 1, 'name' => 'Croque Monsieur',         'description' => 'Toasted ham and cheese sandwich with béchamel sauce',                'price' => 12.49, 'image_url' => null, 'availability' => 'available'],
        ['category_id' => 1, 'name' => 'French Onion Soup',       'description' => 'Slow-cooked onion soup with a gruyère cheese crouton',               'price' => 10.99, 'image_url' => null, 'availability' => 'available'],
        ['category_id' => 1, 'name' => 'Beef Bourguignon',        'description' => 'Braised beef in red wine with mushrooms and pearl onions',           'price' => 18.99, 'image_url' => null, 'availability' => 'available'],

        // Desserts (category_id: 2)
        ['category_id' => 2, 'name' => 'Chocolate Lava Cake',     'description' => 'Warm chocolate cake with a gooey center, served with ice cream',     'price' => 7.99,  'image_url' => null, 'availability' => 'available'],
        ['category_id' => 2, 'name' => 'Gulab Jamun',             'description' => 'Soft milk-solid dumplings soaked in rose-flavored sugar syrup',      'price' => 5.99,  'image_url' => null, 'availability' => 'available'],
        ['category_id' => 2, 'name' => 'Mochi Ice Cream',         'description' => 'Japanese rice cake filled with creamy ice cream',                    'price' => 6.49,  'image_url' => null, 'availability' => 'available'],
        ['category_id' => 2, 'name' => 'Crème Brûlée',            'description' => 'Classic French vanilla custard with a caramelized sugar crust',      'price' => 8.49,  'image_url' => null, 'availability' => 'available'],

        // Drinks (category_id: 3)
        ['category_id' => 3, 'name' => 'Fresh Lemonade',          'description' => 'Freshly squeezed lemonade with mint',                                'price' => 3.99,  'image_url' => null, 'availability' => 'available'],
        ['category_id' => 3, 'name' => 'Mango Lassi',             'description' => 'Chilled yogurt-based mango drink with a hint of cardamom',           'price' => 4.99,  'image_url' => null, 'availability' => 'available'],
        ['category_id' => 3, 'name' => 'Matcha Latte',            'description' => 'Japanese green tea powder with steamed milk',                        'price' => 5.49,  'image_url' => null, 'availability' => 'available'],
        ['category_id' => 3, 'name' => 'Café au Lait',            'description' => 'French-style coffee with equal parts brewed coffee and steamed milk', 'price' => 4.49,  'image_url' => null, 'availability' => 'available'],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO Dishes (category_id, name, description, price, image_url, availability)
        VALUES (:category_id, :name, :description, :price, :image_url, :availability)
    ");

    foreach ($dishes as $dish) {
        $stmt->execute($dish);
    }

    echo "Seeded Dishes.\n";

    // Delivery Addresses (for users 2, 3, 4)
    $addresses = [
        ['user_id' => 2, 'street' => '123 Maple Street',   'city' => 'Montreal', 'postal_code' => 'H2X 1Y4'],
        ['user_id' => 3, 'street' => '456 Oak Avenue',     'city' => 'Gatineau', 'postal_code' => 'J8T 3R2'],
        ['user_id' => 4, 'street' => '789 Pine Boulevard', 'city' => 'Ottawa',   'postal_code' => 'K1A 0A6'],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO Delivery_Address (user_id, street, city, postal_code)
        VALUES (:user_id, :street, :city, :postal_code)
    ");

    foreach ($addresses as $address) {
        $stmt->execute($address);
    }

    echo "Seeded Delivery_Address.\n";

    // Orders
    $orders = [
        [
            'user_id'    => 2,
            'address_id' => 1,
            'subtotal'   => 28.98,
            'taxes'      => 3.77,
            'total'      => 32.75,
            'status'     => 'delivered',
            'notes'      => 'Leave at the door',
        ],
        [
            'user_id'    => 3,
            'address_id' => 2,
            'subtotal'   => 32.98,
            'taxes'      => 4.29,
            'total'      => 37.27,
            'status'     => 'in progress',
            'notes'      => null,
        ],
        [
            'user_id'    => 4,
            'address_id' => 3,
            'subtotal'   => 15.99,
            'taxes'      => 2.08,
            'total'      => 18.07,
            'status'     => 'pending',
            'notes'      => 'Extra napkins please',
        ],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO Orders (user_id, address_id, subtotal, taxes, total, status, notes)
        VALUES (:user_id, :address_id, :subtotal, :taxes, :total, :status, :notes)
    ");

    foreach ($orders as $order) {
        $stmt->execute($order);
    }

    echo "Seeded Orders.\n";

    // Order_Dish
    $orderDishes = [
        // Order 1: Chicken Biryani x1 + Pepperoni Pizza x1
        ['order_id' => 1, 'dish_id' => 1,  'quantity' => 1, 'item_price' => 14.99],
        ['order_id' => 1, 'dish_id' => 8,  'quantity' => 1, 'item_price' => 13.99],

        // Order 2: Salmon Roll x1 + Beef Karahi x1
        ['order_id' => 2, 'dish_id' => 4,  'quantity' => 1, 'item_price' => 15.99],
        ['order_id' => 2, 'dish_id' => 2,  'quantity' => 1, 'item_price' => 16.99],

        // Order 3: Salmon Roll x1
        ['order_id' => 3, 'dish_id' => 4,  'quantity' => 1, 'item_price' => 15.99],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO Order_Dish (order_id, dish_id, quantity, item_price)
        VALUES (:order_id, :dish_id, :quantity, :item_price)
    ");

    foreach ($orderDishes as $item) {
        $stmt->execute($item);
    }

    echo "Seeded Order_Dish.\n";

    echo "\n✅ Database seeded successfully!\n";
    echo "------------------------------------\n";
    echo "Admin login:  admin@cravecart.com / admin1234\n";
    echo "Client login: alice@example.com   / password123\n";
    echo "Client login: bob@example.com     / password123\n";
    echo "Client login: carol@example.com   / password123\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}