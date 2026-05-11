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

use App\Middleware\LocaleMiddleware;
use App\Middleware\SessionTimeoutMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;
use Twig\Environment;

return function (App $app): void {
    // Locale middleware must be first to set language before other middleware
    $app->add(new LocaleMiddleware());
    $app->add(new SessionTimeoutMiddleware());
    // Parses incoming request bodies (form data, JSON)
    $app->addBodyParsingMiddleware();

    // Matches the incoming URL to a route in web-routes.php
    $app->addRoutingMiddleware();

    // Catches any errors or exceptions and logs them without exposing details
    // to end users in production.
    $errorMiddleware = $app->addErrorMiddleware(false, true, false);

    // Render missing-page errors with the app's own branded 404 template.
    $errorMiddleware->setErrorHandler(
        HttpNotFoundException::class,
        function (
            ServerRequestInterface $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) use ($app) {
            $twig = $app->getContainer()->get(Environment::class);
            $response = $app->getResponseFactory()->createResponse(404);
            $response->getBody()->write($twig->render('errors/404.twig'));

            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        }
    );

    // Treat wrong HTTP methods the same way for end users.
    $errorMiddleware->setErrorHandler(
        HttpMethodNotAllowedException::class,
        function (
            ServerRequestInterface $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) use ($app) {
            $twig = $app->getContainer()->get(Environment::class);
            $response = $app->getResponseFactory()->createResponse(404);
            $response->getBody()->write($twig->render('errors/404.twig'));

            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        }
    );

    // Render unexpected server errors through a simple branded fallback page.
    $errorMiddleware->setDefaultErrorHandler(
        function (
            ServerRequestInterface $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) use ($app) {
            $twig = $app->getContainer()->get(Environment::class);
            $response = $app->getResponseFactory()->createResponse(500);
            $response->getBody()->write($twig->render('errors/500.twig'));

            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        }
    );
};
