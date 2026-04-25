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

use App\Domain\Models\Dishes;
use App\Domain\Models\SavedCart;
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
        $this->view->addGlobal('cart_count', $this->getCartCount());
        $this->view->addGlobal('flash', $this->getFlash());

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

    /*
     * flash() - stores a one-time message in the session
     *
     * WHAT: Saves a message that should appear on the next page load only.
     * HOW:  The next render() call pulls it from the session with getFlash()
     *       and then removes it so it doesn't keep showing forever.
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /*
     * getFlash() - retrieves and clears the current flash message
     *
     * WHAT: Returns the flash payload for Twig to render.
     * HOW:  Unsets the session value immediately after reading so the message
     *       only appears once.
     */
    protected function getFlash(): ?array
    {
        if (empty($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return $flash;
    }

    /*
     * getCartCount() - returns the total number of items currently in the cart
     *
     * WHAT: Calculates the badge number shown on the navbar cart icon.
     * HOW:  Reads the session cart so guests and logged-in clients see the same
     *       current quantity in the header.
     */
    protected function getCartCount(): int
    {
        $cart = $this->getCurrentCart();
        return array_sum($cart);
    }

    /*
     * getCurrentCart() - returns the session cart, hydrating from saved_cart if needed
     *
     * WHAT: Gives controllers one consistent cart source to read from.
     * HOW:  Clients lazily hydrate the session cart from the DB on first use.
     *       Guests simply use the session-only cart.
     */
    protected function getCurrentCart(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? '';

        if ($role === 'administrator') {
            return [];
        }

        if ($userId > 0) {
            $syncedUserId = (int) ($_SESSION['cart_synced_user_id'] ?? 0);
            if (!isset($_SESSION['cart']) || $syncedUserId !== $userId) {
                $this->refreshSessionCartFromSavedCart($userId);
            }
        }

        return $this->normaliseCart($_SESSION['cart'] ?? []);
    }

    /*
     * setSessionCart() - replaces the session cart with a cleaned copy
     *
     * WHAT: Persists the cart structure in the current PHP session.
     * HOW:  Filters invalid keys/quantities before saving so controllers can
     *       trust the shape of the data later.
     */
    protected function setSessionCart(array $cart): void
    {
        $_SESSION['cart'] = $this->normaliseCart($cart);
    }

    /*
     * mergeSessionCartIntoSavedCart() - merges guest/session items into saved_cart
     *
     * WHAT: Used after login so any pre-login cart items get added to the
     *       user's persistent cart instead of being lost.
     * HOW:  Replays each session cart item into SavedCart::addItem(), then
     *       refreshes the session cart from the DB so both layers match.
     */
    protected function mergeSessionCartIntoSavedCart(int $userId): void
    {
        $cart = $this->normaliseCart($_SESSION['cart'] ?? []);

        foreach ($cart as $dishId => $quantity) {
            $dish = Dishes::findById($dishId);
            if ($dish === null) {
                continue;
            }

            SavedCart::addItem($userId, $dishId, $quantity, (float) $dish->price);
        }

        $this->refreshSessionCartFromSavedCart($userId);
    }

    /*
     * refreshSessionCartFromSavedCart() - rebuilds the session cart from DB rows
     *
     * WHAT: Keeps the session cart in sync with the persistent cart for clients.
     * HOW:  Reads every saved_cart row for the user and stores dish_id => quantity
     *       in the session.
     */
    protected function refreshSessionCartFromSavedCart(int $userId): void
    {
        $items = SavedCart::findByUserId($userId);
        $cart = [];

        foreach ($items as $item) {
            $cart[(int) $item->dish_id] = (int) $item->quantity;
        }

        $_SESSION['cart'] = $cart;
        $_SESSION['cart_synced_user_id'] = $userId;
    }

    /*
     * normaliseCart() - sanitizes the raw session cart into dish_id => quantity
     *
     * WHAT: Ensures cart keys are positive dish IDs and values are positive ints.
     * HOW:  Invalid rows are ignored so corrupted session data does not break
     *       cart logic.
     */
    protected function normaliseCart(array $cart): array
    {
        $normalised = [];

        foreach ($cart as $dishId => $quantity) {
            $dishId = (int) $dishId;
            $quantity = (int) $quantity;

            if ($dishId > 0 && $quantity > 0) {
                $normalised[$dishId] = $quantity;
            }
        }

        return $normalised;
    }
}
