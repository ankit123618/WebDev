<?php
declare(strict_types=1);

namespace helpers;
/**
 * Sends a redirect response and stops execution.
 */
function redirect($url)
{
    header("Location: $url");
    exit;
}

/**
 * Loads a view file and exposes the supplied data as local variables.
 */
function view($file, $data = [])
{
    extract($data);

    require __DIR__ . "/../views/" . $file . ".php";
}

/**
 * Normalizes an application path so it always starts with a leading slash.
 */
function url($path = '')
{
    return '/' . ltrim($path, '/');
}
