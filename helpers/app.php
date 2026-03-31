<?php
/**
 * Configures PHP error display rules for the current runtime.
 *
 * This keeps noisy errors visible in development while hiding them in production.
 */
function behaviour()
{
    $appEnv = getenv('APP_ENV') ?: 'production';
    $appDebug = getenv('APP_DEBUG') ?: 'false';

    $appDebug = in_array(strtolower($appDebug), ['1', 'true', 'yes', 'on'], true);

    if ($appEnv === 'development' || $appDebug) {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', 0);
        error_reporting(0);
    }
}
