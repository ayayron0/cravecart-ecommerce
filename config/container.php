<?php

declare(strict_types=1);

/*
 * container.php — Dependency Injection container
 *
 * WHAT: Tells Slim how to build the objects the app needs (Twig, RedBeanPHP, etc.)
 * HOW:  Each entry is a key (the class name) and a function that builds it.
 *       When a controller needs Twig, Slim looks it up here and runs the function.
 */

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UriFactory;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$definitions = [

    /*
     * Twig — the templating engine
     * Loads all .twig files from the app/Views folder.
     * Also registers basePath and asset_url() so they work in every template.
     */
    Environment::class => function (): Environment {
        $loader = new FilesystemLoader(APP_VIEWS_PATH);
        $twig   = new Environment($loader, [
            'cache' => false, // set to a folder path in production for speed
            'debug' => true,
        ]);

        // Makes {{ basePath }} available in every template
        $twig->addGlobal('basePath', APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '');

        // Makes {{ session.role }}, {{ session.name }} etc. available in every template
        $twig->addGlobal('session', $_SESSION ?? []);

        // Makes {{ asset_url('/css/main.css') }} available in every template
        $twig->addFunction(new \Twig\TwigFunction('asset_url', 'asset_url'));

        // Makes {{ __('nav.home') }} available in every template for i18n
        $twig->addFunction(new \Twig\TwigFunction('__', '__'));
        $twig->addFunction(new \Twig\TwigFunction('trans', 'trans'));
        $twig->addFunction(new \Twig\TwigFunction('has_translation', 'has_translation'));

        // Makes {{ currentLocale() }} and {{ supportedLocales() }} available in every template as functions
        // Using functions instead of globals so the locale can change dynamically during the request
        $twig->addFunction(new \Twig\TwigFunction('currentLocale', 'get_locale'));
        $twig->addFunction(new \Twig\TwigFunction('supportedLocales', 'get_supported_locales'));
        $twig->addFunction(new \Twig\TwigFunction('htmlLang', 'get_html_lang'));

        return $twig;
    },

    /*
     * Slim App — the core of the application
     * This is where RedBeanPHP connects to the database,
     * routes are registered, and middleware is applied.
     */
    App::class => function (ContainerInterface $container): App {

        // Connect RedBeanPHP to the database using credentials from env.php
        $db = require __DIR__ . '/env.php';
        \RedBeanPHP\R::setup(
            sprintf('mysql:host=%s;dbname=%s', $db['host'], $db['database']),
            $db['username'],
            $db['password']
        );

        // Create the Slim app using the DI container
        $app = AppFactory::createFromContainer($container);

        // Set the base path so routes work in a Wampoon subdirectory
        $app->setBasePath(APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '');

        // Register all routes from app/Routes/web-routes.php
        (require_once __DIR__ . '/../app/Routes/web-routes.php')($app);

        // Register middleware from config/middleware.php
        (require_once __DIR__ . '/middleware.php')($app);

        return $app;
    },

    // These HTTP factories are required internally by Slim
    ResponseFactoryInterface::class      => fn () => new ResponseFactory(),
    ServerRequestFactoryInterface::class => fn () => new ServerRequestFactory(),
    StreamFactoryInterface::class        => fn () => new StreamFactory(),
    UriFactoryInterface::class           => fn () => new UriFactory(),
];

return $definitions;


