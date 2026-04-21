<?php

/*
 * WHAT:  This is the application bootstrap — the very first file that runs on every request.
 * HOW:   index.php (the front controller) calls this file once.
 *        It sets up everything the app needs before handling a request:
 *          1. Loads Composer's autoloader so all classes are available
 *          2. Starts the PHP session so we can remember logged-in users
 *          3. Loads constants and helper functions
 *          4. Builds the DI container (wires together Twig, RedBeanPHP, Slim, etc.)
 *          5. Returns the fully configured Slim App instance back to index.php
 */

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\App;

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if ($autoloadPath !== false && is_file($autoloadPath)) {
    require $autoloadPath;
} else {
    die('<br><strong>Error:</strong> Composer autoload file not found. <br> <br><strong>Fix:</strong> Please run the following command in a <strong>VS Code command prompt terminal</strong> to install the missing dependencies: <br><strong>Command (keep the double quotes):</strong> <span style="background-color: yellow;"> "../../composer.bat" update </span><br> For more details, refer to: <br><a href="https://github.com/frostybee/slim-mvc?tab=readme-ov-file#how-do-i-usedeploy-this-template" target="_blank">Configuration instructions in README.md</a>');
}

// Start the session so $_SESSION is available everywhere in the app.
session_start();

// Load the app's global constants.
require_once __DIR__ . '/constants.php';
// Include the global functions that will be used across the app's various components.
require __DIR__ . '/functions.php';

// Configure the DI container and load dependencies.
$definitions = require_once __DIR__ . '/container.php';

// Build DI container instance.
//@see https://php-di.org/
$container = (new ContainerBuilder())
    ->addDefinitions($definitions)
    ->build();

// Create and return an App instance.
$app = $container->get(App::class);
return $app;
