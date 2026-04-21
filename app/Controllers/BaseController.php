<?php

declare(strict_types=1);

namespace App\Controllers;

/*
 * BaseController — parent class for all controllers
 *
 * WHAT: Every controller extends this class to get access to Twig (render)
 *       and redirect without repeating the same code everywhere.
 * HOW:  The DI container passes itself in — the controller uses it to
 *       fetch the Twig instance.
 */

use DI\Container;
use Twig\Environment;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

abstract class BaseController
{
    // Twig templating engine — used to render .twig view files
    protected Environment $view;

    // The DI container — used to fetch any service your controller needs
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->view      = $container->get(Environment::class);
    }

    /*
     * render() — display a Twig template and return an HTTP response
     *
     * WHAT: Renders a .twig file and writes the HTML into the response.
     * HOW:  $template is a path relative to app/Views/ (e.g. 'home.twig')
     *       $data is an array of variables you want available in the template.
     *
     * Example:
     *   return $this->render($response, 'home.twig', ['title' => 'Home']);
     *   Then in home.twig: {{ title }}
     */
    protected function render(Response $response, string $template, array $data = []): Response
    {
        // Refresh the session global on every render so the navbar always reflects
        // the current login state rather than the state when the container first booted.
        $this->view->addGlobal('session', $_SESSION ?? []);

        $html = $this->view->render($template, $data);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /*
     * redirect() — send the user to a different route
     *
     * WHAT: Returns a 302 redirect response to a named route.
     * HOW:  Uses the route name you set with ->setName() in web-routes.php.
     *
     * Example:
     *   return $this->redirect($request, $response, 'home.index');
     */
    protected function redirect(Request $request, Response $response, string $route_name, array $uri_args = [], array $query_params = []): Response
    {
        $route_parser = RouteContext::fromRequest($request)->getRouteParser();
        $url          = $route_parser->urlFor($route_name, $uri_args, $query_params);
        return $response->withStatus(302)->withHeader('Location', $url);
    }
}
