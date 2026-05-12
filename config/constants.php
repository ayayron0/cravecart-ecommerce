<?php

declare(strict_types=1);

// Define the path of the application's root directory.
define('APP_BASE_DIR_PATH', dirname(__DIR__, 1));

// Holds the app's base path without a leading slash.
// Examples:
//   - '' for a VPS/domain root like https://example.com/
//   - 'cravecart-ecommerce' for a subfolder install like http://localhost/cravecart-ecommerce/
//
// Priority:
//   1. APP_ROOT_DIR environment variable if explicitly provided
//   2. Auto-detected from the current script path
$configuredAppRoot = $_ENV['APP_ROOT_DIR'] ?? getenv('APP_ROOT_DIR') ?: null;

if (is_string($configuredAppRoot)) {
    $configuredAppRoot = trim($configuredAppRoot);
}

if ($configuredAppRoot === null || $configuredAppRoot === false) {
    $scriptDirectory = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDirectory = str_replace('\\', '/', dirname($scriptDirectory));
    $configuredAppRoot = ($scriptDirectory === '/' || $scriptDirectory === '.' || $scriptDirectory === '')
        ? ''
        : trim($scriptDirectory, '/');
} else {
    $configuredAppRoot = trim((string) $configuredAppRoot, "/\\");
}

define('APP_ROOT_DIR_NAME', $configuredAppRoot);

// Define the path of the application's views directory.
const APP_VIEWS_PATH = APP_BASE_DIR_PATH . '/app/Views';

// Define the public assets URL path and full filesystem path.
// Because Apache/Nginx serves the app from the public/ directory itself,
// browser URLs should point to /assets/... rather than /public/assets/...
const APP_ASSETS_DIR      = '/assets';
const APP_ASSETS_DIR_PATH = APP_BASE_DIR_PATH . '/public/assets';

// Debug mode — true in dev, false in production.
define('APP_DEBUG_MODE', ($_ENV['APP_ENV'] ?? 'dev') === 'dev');



//* HTTP response status code.
const HTTP_OK = 200;
const HTTP_CREATED = 201;
const HTTP_NO_CONTENT = 204;
const HTTP_METHOD_NOT_ALLOWED = 405;
const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
const HTTP_NOT_FOUND = 404;
//* HTTP response headers.
const HEADERS_CONTENT_TYPE = "Content-Type";
//* Supported Media Types.
const APP_MEDIA_TYPE_JSON = "application/json";
const APP_MEDIA_TYPE_XML = "application/xml";
const APP_MEDIA_TYPE_YAML = "application/yaml";
