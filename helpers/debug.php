<?php
declare(strict_types=1);

namespace helpers;
/**
 * Dumps a value for debugging and stops execution immediately.
 */
function dd($data)
{
    echo "<pre>";
    print_r($data);
    die();
}
