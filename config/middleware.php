<?php

declare(strict_types=1);

/*
 * middleware.php — Middleware registration
 *
 * WHAT: Middleware runs on every request before it reaches a controller.
 * HOW:  Each line below adds a layer. They run in reverse order (last added = first to run).
 *
 * The three lines below are the minimum needed for a Slim app:
 *   1. Body parsing   — lets you read POST form data in controllers
 *   2. Routing        — matches the URL to the correct route
 *   3. Error handling — catches errors and shows a readable message
 */

use Slim\App;

return function (App $app): void {
    // Parses incoming request bodies (form data, JSON)
    $app->addBodyParsingMiddleware();

    // Matches the incoming URL to a route in web-routes.php
    $app->addRoutingMiddleware();

    // Catches any errors or exceptions and displays them
    // Arguments: (show error details, log errors, display errors)
    // Set the first argument to false in production so users don't see error details
    $app->addErrorMiddleware(true, true, true);
};
