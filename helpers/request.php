<?php
declare(strict_types=1);

namespace helpers;
/**
 * Returns the full request payload or a single request value.
 */
function request($key = null)
{
    if ($key === null) {
        return $_REQUEST;
    }

    return $_REQUEST[$key] ?? null;
}

/**
 * Returns the full POST payload or a single POST value.
 */
function post($key = null, $default = null)
{
    if ($key === null) {
        return $_POST;
    }

    return $_POST[$key] ?? $default;
}

/**
 * Returns the full query string payload or a single GET value.
 */
function get($key = null, $default = null)
{
    if ($key === null) {
        return $_GET;
    }

    return $_GET[$key] ?? $default;
}

/**
 * Returns the full server array or a single server value.
 */
function server($key = null, $default = null)
{
    if ($key === null) {
        return $_SERVER;
    }

    return $_SERVER[$key] ?? $default;
}

/**
 * Checks whether a POST field exists in the current request.
 */
function has_post($key)
{
    return array_key_exists($key, $_POST);
}

/**
 * Checks whether a GET field exists in the current request.
 */
function has_get($key)
{
    return array_key_exists($key, $_GET);
}

/**
 * Reads a request value with POST taking precedence over GET.
 */
function input($key, $default = null)
{
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }

    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }

    return $default;
}

/**
 * Returns a trimmed POST string or the supplied default.
 */
function post_string($key, $default = '')
{
    $value = post($key, $default);

    return is_string($value) ? trim($value) : $default;
}

/**
 * Returns a trimmed GET string or the supplied default.
 */
function get_string($key, $default = '')
{
    $value = get($key, $default);

    return is_string($value) ? trim($value) : $default;
}

/**
 * Returns the current HTTP request method in uppercase.
 */
function request_method()
{
    return strtoupper((string) server('REQUEST_METHOD', 'GET'));
}

/**
 * Reads a cookie value or returns a default when it is missing.
 */
function cookie_get(string $key, $default = null)
{
    return $_COOKIE[$key] ?? $default;
}

/**
 * Stores a cookie using secure defaults for path, HTTP-only access, and same-site behavior.
 */
function cookie_set(string $key, string $value, int $expiresAt): bool
{
    $https = server('HTTPS', '');
    $isSecure = $https !== '' && $https !== 'off';

    return setcookie($key, $value, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Expires a cookie immediately and removes it from the current request state.
 */
function cookie_delete(string $key): bool
{
    unset($_COOKIE[$key]);

    return cookie_set($key, '', time() - 3600);
}
