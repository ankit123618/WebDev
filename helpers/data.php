<?php
declare(strict_types=1);

namespace helpers;
/**
 * Encodes a value as JSON and sends the matching response header.
 */
function json($data)
{
    header('Content-Type: application/json');
    return json_encode($data);
    exit;
}

/**
 * Decodes a JSON string into PHP data.
 */
function json_decode($data, $assoc = true)
{
    return json_decode($data, $assoc);
}

/**
 * Escapes output for safe HTML rendering.
 */
function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Returns the previously submitted POST value for a field.
 */
function old($key)
{
    return $_POST[$key] ?? '';
}

/**
 * Returns the previous POST value or a fallback default.
 */
function old_or($key, $default = '')
{
    return $_POST[$key] ?? $default;
}
