<?php

declare(strict_types=1);

use App\Helpers\Core\AppSettings;
use App\Helpers\Core\JsonRenderer;
use App\Helpers\Core\PDOService;
use App\Middleware\ExceptionMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Slim\Factory\AppFactory;
use Slim\App;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UriFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
// REMOVED: PhpRenderer imports, ADDED: Twig Environment + FilesystemLoader

$definitions = [
    AppSettings::class => function () {
        return new AppSettings(
            require_once __DIR__ . '/settings.php'
        );
    },

    App::class => function (ContainerInterface $container) {
        $app = AppFactory::createFromContainer($container);
        $app->setBasePath(APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '');
        (require_once __DIR__ . '/../app/Routes/web-routes.php')($app);
        (require_once __DIR__ . '/middleware.php')($app);
        return $app;
    },

    // CHANGED: Replaced PhpRenderer with Twig Environment
    Environment::class => function (ContainerInterface $container): Environment {
        $loader = new FilesystemLoader(APP_VIEWS_PATH);
        $twig = new Environment($loader, [
            'cache' => false,
            'debug' => true,
        ]);
        $basePath = APP_ROOT_DIR_NAME ? '/' . APP_ROOT_DIR_NAME : '';
        $twig->addGlobal('basePath', $basePath);
        return $twig;
    },

    PDOService::class => function (ContainerInterface $container): PDOService {
        $db_config = $container->get(AppSettings::class)->get('db');
        return new PDOService($db_config);
    },

    // HTTP factories (unchanged)
    ResponseFactoryInterface::class => fn() => new ResponseFactory(),
    ServerRequestFactoryInterface::class => fn() => new ServerRequestFactory(),
    StreamFactoryInterface::class => fn() => new StreamFactory(),
    UriFactoryInterface::class => fn() => new UriFactory(),

    // CHANGED: Removed PhpRenderer from ExceptionMiddleware, now renders error string directly
    ExceptionMiddleware::class => function (ContainerInterface $container) {
        $settings = $container->get(AppSettings::class)->get('error');
        return new ExceptionMiddleware(
            $container->get(ResponseFactoryInterface::class),
            $container->get(JsonRenderer::class),
            // CHANGED: Pass Twig Environment instead of PhpRenderer
            $container->get(Environment::class),
            null,
            (bool) $settings['display_error_details'],
        );
    },
];

return $definitions;