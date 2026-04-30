<?php

declare(strict_types=1);

/**
 * i18n - Internationalization Configuration and Helper Functions
 *
 * This file provides a simple but powerful internationalization system
 * for the CraveCart application. It supports multiple languages,
 * parameter substitution, and seamless Twig integration.
 *
 * Usage in PHP:
 *   echo __('nav.home');                    // Outputs: "Home"
 *   echo __('dishes.minutes', ['count' => 30]);  // Outputs: "30 minutes"
 *
 * Usage in Twig templates:
 *   {{ __('nav.home') }}                    // Outputs: "Home"
 *   {{ __('dishes.minutes', {'count': 30}) }}  // Outputs: "30 minutes"
 *
 * Changing language:
 *   set_locale('es');  // Switch to Spanish
 *   set_locale('fr');  // Switch to French
 */

// Define the translations directory path
const APP_TRANSLATIONS_PATH = APP_BASE_DIR_PATH . '/config/translations';

// Supported locales
const SUPPORTED_LOCALES = ['en', 'es', 'fr'];

// Default locale
const DEFAULT_LOCALE = 'en';

/**
 * Get the current locale from session, cookie, or default
 *
 * @return string The current locale code (e.g., 'en', 'es', 'fr')
 */
function get_locale(): string
{
    // Check session first
    if (isset($_SESSION['locale']) && in_array($_SESSION['locale'], SUPPORTED_LOCALES)) {
        return $_SESSION['locale'];
    }

    // Check cookie
    if (isset($_COOKIE['locale']) && in_array($_COOKIE['locale'], SUPPORTED_LOCALES)) {
        $_SESSION['locale'] = $_COOKIE['locale'];
        return $_COOKIE['locale'];
    }

    // Try to detect from browser's Accept-Language header
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLocale = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if (in_array($browserLocale, SUPPORTED_LOCALES)) {
            $_SESSION['locale'] = $browserLocale;
            setcookie('locale', $browserLocale, time() + (86400 * 30), '/'); // 30 days
            return $browserLocale;
        }
    }

    return DEFAULT_LOCALE;
}

/**
 * Set the locale for the current user
 *
 * @param string $locale The locale code to set (e.g., 'en', 'es', 'fr')
 * @return bool True if the locale was set successfully
 */
function set_locale(string $locale): bool
{
    if (!in_array($locale, SUPPORTED_LOCALES)) {
        return false;
    }

    $_SESSION['locale'] = $locale;
    setcookie('locale', $locale, time() + (86400 * 30), '/'); // 30 days

    return true;
}

/**
 * Get all supported locales with their native names
 *
 * @return array Array of locale codes with their display names
 */
function get_supported_locales(): array
{
    return [
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
    ];
}

/**
 * Check if a locale is supported
 *
 * @param string $locale The locale code to check
 * @return bool True if the locale is supported
 */
function is_locale_supported(string $locale): bool
{
    return in_array($locale, SUPPORTED_LOCALES);
}

/**
 * Cache for loaded translations to avoid repeated file reads
 */
static $translationsCache = [];

/**
 * Load translations for a specific locale
 *
 * @param string $locale The locale to load translations for
 * @return array The translations array
 */
function load_translations(string $locale): array
{
    global $translationsCache;

    // Return from cache if already loaded
    if (isset($translationsCache[$locale])) {
        return $translationsCache[$locale];
    }

    $translationFile = APP_TRANSLATIONS_PATH . '/' . $locale . '/messages.json';

    if (!file_exists($translationFile)) {
        // Fall back to default locale if translation file not found
        if ($locale !== DEFAULT_LOCALE) {
            return load_translations(DEFAULT_LOCALE);
        }
        return [];
    }

    $content = file_get_contents($translationFile);
    $translations = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('i18n Error: Invalid JSON in ' . $translationFile . ': ' . json_last_error_msg());
        return [];
    }

    $translationsCache[$locale] = $translations;

    return $translations;
}

/**
 * Clear the translations cache (useful when translations are updated)
 */
function clear_translations_cache(): void
{
    global $translationsCache;
    $translationsCache = [];
}

/**
 * Get a translation string by key with optional parameter substitution
 *
 * @param string $key The translation key (dot notation, e.g., 'nav.home')
 * @param array $params Optional parameters for substitution (e.g., ['count' => 5])
 * @param string|null $locale Optional locale override
 * @return string The translated string, or the key if not found
 */
function __(string $key, array $params = [], ?string $locale = null): string
{
    if ($locale === null) {
        $locale = get_locale();
    }

    $translations = load_translations($locale);
    $keys = explode('.', $key);
    $value = $translations;

    // Traverse the nested array using dot notation
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            // Try fallback to default locale
            if ($locale !== DEFAULT_LOCALE) {
                return __($key, $params, DEFAULT_LOCALE);
            }
            // Return key as fallback
            return $key;
        }
        $value = $value[$k];
    }

    if (!is_string($value)) {
        if ($locale !== DEFAULT_LOCALE) {
            return __($key, $params, DEFAULT_LOCALE);
        }
        return $key;
    }

    // Substitute parameters (format: %param_name%)
    if (!empty($params)) {
        $replace = [];
        foreach ($params as $paramKey => $paramValue) {
            $replace['%' . $paramKey . '%'] = (string) $paramValue;
        }
        $value = strtr($value, $replace);
    }

    return $value;
}

/**
 * Alias for __() function
 *
 * @param string $key The translation key
 * @param array $params Optional parameters for substitution
 * @param string|null $locale Optional locale override
 * @return string The translated string
 */
function trans(string $key, array $params = [], ?string $locale = null): string
{
    return __($key, $params, $locale);
}

/**
 * Check if a translation key exists
 *
 * @param string $key The translation key to check
 * @param string|null $locale Optional locale override
 * @return bool True if the key exists
 */
function has_translation(string $key, ?string $locale = null): bool
{
    if ($locale === null) {
        $locale = get_locale();
    }

    $translations = load_translations($locale);
    $keys = explode('.', $key);
    $value = $translations;

    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            if ($locale !== DEFAULT_LOCALE) {
                return has_translation($key, DEFAULT_LOCALE);
            }
            return false;
        }
        $value = $value[$k];
    }

    return is_string($value);
}

/**
 * Get the locale-specific HTML lang attribute value
 *
 * @return string The lang attribute value (e.g., 'en', 'es', 'fr')
 */
function get_html_lang(): string
{
    return get_locale();
}

/**
 * Get the locale-specific URL for language switcher
 *
 * @param string $locale The target locale
 * @param string|null $path Optional path to append
 * @return string The URL with locale parameter
 */
function locale_url(string $locale, ?string $path = null): string
{
    $currentPath = $path ?? $_SERVER['REQUEST_URI'] ?? '/';
    // Remove existing locale parameter if present
    $url = preg_replace('/[?&]locale=[a-z]{2}/', '', $currentPath);
    $separator = strpos($url, '?') !== false ? '&' : '?';
    return $url . $separator . 'locale=' . $locale;
}

// Initialize locale on every request
if (session_status() === PHP_SESSION_ACTIVE) {
    // Locale will be determined by get_locale() when needed
}