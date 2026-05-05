<?php

declare(strict_types=1);

namespace App\Controllers;

use DI\Container;
use App\Domain\Models\Cuisines;
use App\Domain\Models\Dishes;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/*
 * HomeController — handles public-facing pages that don't belong to a specific feature.
 *
 * WHAT: Renders the home page, about page, and handles the AJAX search endpoint.
 * HOW:  index() loads all cuisines for the home page cards.
 *       search() queries the database and returns JSON for the live search bar.
 *       apiCuisines() exposes a simple JSON endpoint for external integrations.
 *       about() renders the static about page.
 *       showLogin() checks for a session timeout flag and passes an error message
 *       to the login view when the user was logged out automatically.
 */
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
        $data['cuisines'] = Cuisines::getAll();

        $data['data'] = [
            'title' => 'Home',
            'message' => 'Welcome to the home page',
        ];

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
        if (strlen($query) < 2) {
            $response->getBody()->write(json_encode(['error' => 'Search query must be at least 2 characters long']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $json = $this->withCleanJson(static function () use ($query): string {
            return json_encode(Dishes::searchDish($query));
        });

        // Write the array as a JSON string to the response body, then set the
        // Content-Type header so the browser knows to parse it as JSON.
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Simple API endpoint for other software to read cuisine data as JSON.
    public function apiCuisines(Request $request, Response $response, array $args): Response
    {
        $payload = $this->withCleanJson(static function (): string {
            $cuisineBeans = Cuisines::getAll();

            $cuisines = array_map(static function ($cuisine): array {
                return [
                    'id' => (int) $cuisine->id,
                    'name' => (string) $cuisine->name,
                    'code' => (string) $cuisine->code,
                    'slug' => (string) $cuisine->slug,
                    'description' => $cuisine->description ? (string) $cuisine->description : null,
                    'image_url' => $cuisine->image_url ? (string) $cuisine->image_url : null,
                ];
            }, $cuisineBeans);

            return json_encode([
                'count' => count($cuisines),
                'cuisines' => $cuisines,
            ]);
        });

        $response->getBody()->write($payload);

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function withCleanJson(callable $callback): string
    {
        $previousDisplayErrors = ini_get('display_errors');
        $previousErrorReporting = error_reporting();

        ini_set('display_errors', '0');
        error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        try {
            return $callback();
        } finally {
            error_reporting($previousErrorReporting);
            ini_set('display_errors', (string) $previousDisplayErrors);
        }
    }

    public function error(Request $request, Response $response, array $args): Response
    {
        return $this->render($response, 'errorView.php');
    }
}
