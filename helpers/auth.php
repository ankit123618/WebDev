<?php
declare(strict_types=1);

namespace helpers;
/**
 * Returns the current CSRF token from the application CSRF service.
 */
function csrf_token()
{
    return \core\app::container()->get(\core\csrf::class)->generate();
}

/**
 * Reads a session value or stores one when a value is provided.
 */
function session($key, $value = null)
{
    if ($value === null) {
        return $_SESSION[$key] ?? null;
    }

    $_SESSION[$key] = $value;
}

/**
 * Checks whether a session key exists.
 */
function has_session($key)
{
    return array_key_exists($key, $_SESSION);
}

/**
 * Removes a value from the session.
 */
function forget_session($key)
{
    unset($_SESSION[$key]);
}

/**
 * Reads and forgets a session value in one step.
 */
function pull_session($key, $default = null)
{
    if (!array_key_exists($key, $_SESSION)) {
        return $default;
    }

    $value = $_SESSION[$key];
    unset($_SESSION[$key]);

    return $value;
}

/**
 * Stores a flash message for the next request.
 */
function flash($key, $message, $type = 'info')
{
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }

    $_SESSION['_flash'][$key] = [
        'message' => $message,
        'type' => $type,
    ];
}

/**
 * Checks whether a flash message exists for the given key.
 */
function has_flash($key)
{
    return isset($_SESSION['_flash'][$key]);
}

/**
 * Returns and removes a single flash message.
 */
function pull_flash($key, $default = null)
{
    if (!has_flash($key)) {
        return $default;
    }

    $value = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    if ($_SESSION['_flash'] === []) {
        unset($_SESSION['_flash']);
    }

    return $value;
}

/**
 * Returns and clears all queued flash messages.
 */
function pull_flashes()
{
    $flashes = $_SESSION['_flash'] ?? [];

    unset($_SESSION['_flash']);

    return is_array($flashes) ? $flashes : [];
}
