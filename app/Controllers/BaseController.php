<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Core\AppSettings;
use Twig\Environment;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

abstract class BaseController
{
    // CHANGED: PhpRenderer -> Twig Environment
    protected Environment $twig;
    protected AppSettings $settings;
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = $container->get(AppSettings::class);
        // CHANGED: PhpRenderer -> Twig Environment
        $this->twig = $container->get(Environment::class);
    }

    protected function render(Response $response, string $view_file, array $data = []): Response
    {
        $response = $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        // CHANGED: Twig renders to string first, then written to response body
        $response->getBody()->write($this->twig->render($view_file, $data));
        return $response;
    }

    protected function redirect(Request $request, Response $response, string $route_name, array $uri_args = [], array $query_params = []): Response
    {
        $route_parser = RouteContext::fromRequest($request)->getRouteParser();
        $target_uri = $route_parser->urlFor($route_name, $uri_args, $query_params);
        return $response->withStatus(302)->withHeader('Location', $target_uri);
    }
}