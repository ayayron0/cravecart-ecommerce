<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use App\Domain\Models\Cuisines;
use App\Domain\Models\Dishes;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomeController extends BaseController
{
    //NOTE: Passing the entire container violates the Dependency Inversion Principle and creates a service locator anti-pattern.
    // However, it is a simple and effective way to pass the container to the controller given the small scope of the application and the fact that this application is to be used in a classroom setting where students are not yet familiar with the Dependency Inversion Principle.
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        //$data['flash'] = $this->flash->getFlashMessage();
        //echo $data['message'] ;exit;

        $data['cuisines'] = Cuisines::getAll();

        $query = $request->getQueryParams();

        if (($query['logged_out'] ?? null) === '1') {
            $data['success'] = 'You have been successfully signed out.';
        } elseif (($query['registered'] ?? null) === '1') {
            $data['success'] = 'Account created! Welcome to CraveCart.';
        } elseif (($query['loggedin'] ?? null) === '1') {
            $data['success'] = 'Welcome back!';
        }

        $data['data'] = [
            'title' => 'Home',
            'message' => 'Welcome to the home page',
        ];

        //dd($data);
        //var_dump($this->session); exit;
        return $this->render($response, 'home.twig', $data);
    }

    // Renders the static About page.
    public function about(Request $request, Response $response, array $args): Response
    {
        return $this->render($response, 'about.twig');
    }

    // AJAX search endpoint — returns JSON, not a rendered page.
    // Called by search.js whenever the user types in the search bar.
    public function search(Request $request, Response $response, array $args): Response
    {
        // Read the ?q= value from the URL. Falls back to empty string if missing.
        $query = $request->getQueryParams()['q'] ?? '';

        // Reject queries shorter than 2 characters to avoid returning the entire
        // dishes table and to reduce unnecessary database load.
        if(strlen($query) < 2){
            $response->getBody()->write(json_encode(['error' => 'Search query must be at least 2 characters long']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $data['dishes'] = Dishes::searchDish($query);

        // Write the array as a JSON string to the response body, then set the
        // Content-Type header so the browser knows to parse it as JSON.
        $response->getBody()->write(json_encode($data['dishes']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function error(Request $request, Response $response, array $args): Response
    {
        return $this->render($response, 'errorView.php');
    }
}
