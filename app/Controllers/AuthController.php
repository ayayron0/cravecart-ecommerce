<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends BaseController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function loginForm(Request $request, Response $response, array $args): Response
    {
        return $this->render($response, 'login.twig');
    }

    public function login(Request $request, Response $response, array $args): Response
    {
        // TODO: implement login logic
        return $this->redirect($request, $response, 'home.index');
    }

    public function registerForm(Request $request, Response $response, array $args): Response
    {
        return $this->render($response, 'register.twig');
    }

    public function register(Request $request, Response $response, array $args): Response
    {
        // TODO: implement registration logic
        return $this->redirect($request, $response, 'home.index');
    }

    public function logout(Request $request, Response $response, array $args): Response
    {
        // TODO: implement logout logic
        return $this->redirect($request, $response, 'home.index');
    }
}
