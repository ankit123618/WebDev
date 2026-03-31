<?php
declare(strict_types=1);

namespace controllers;

/**
 * Provides the example user route used for simple parameterized responses.
 *
 * The controller is intentionally minimal and currently echoes the requested user ID.
 */
class user
{
    /**
     * Displays the requested user identifier.
     */
    public function show(string $id): void
    {
        echo "User ID: " . $id;
    }
}
