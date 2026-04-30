<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * LocaleMiddleware - Handles locale/language switching for i18n
 *
 * This middleware checks for a 'locale' query parameter in the request
 * and sets the user's locale accordingly. It also sets the locale
 * from session/cookie if no query parameter is present.
 *
 * Usage: Visit ?locale=es to switch to Spanish, ?locale=fr for French, etc.
 */
class LocaleMiddleware implements MiddlewareInterface
{
    /**
     * Process the request and set the locale if requested
     *
     * @param ServerRequestInterface $request The request
     * @param RequestHandlerInterface $handler The handler
     * @return ResponseInterface The response
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check for locale query parameter
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['locale']) && is_string($queryParams['locale'])) {
            $locale = $queryParams['locale'];
            if (function_exists('set_locale') && is_locale_supported($locale)) {
                set_locale($locale);

                // Redirect to remove the query parameter and show the new locale
                // This ensures the page reloads with the correct language
                $uri = $request->getUri();
                $newUri = $uri->withQuery(http_build_query(array_diff_key($queryParams, ['locale' => ''])));

                $response = new \Slim\Psr7\Response();
                return $response
                    ->withHeader('Location', (string) $newUri)
                    ->withStatus(302);
            }
        }

        // Continue with the request
        return $handler->handle($request);
    }
}
