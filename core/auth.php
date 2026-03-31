<?php
declare(strict_types=1);

namespace core;

use function helpers\session;

/**
 * Wraps session-backed authentication state behind a small query API.
 *
 * Controllers use this service to read the current user identity and role safely.
 */
class auth
{
    /**
     * Returns the authenticated user ID stored in the session.
     */
    public function user(): mixed
    {
        return session('user');
    }

    /**
     * Returns the authenticated user's email address from the session.
     */
    public function email(): mixed
    {
        return session('email');
    }

    /**
     * Indicates whether a user is currently authenticated.
     */
    public function check(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Returns the current user's role from the session.
     */
    public function role(): mixed
    {
        return session('role');
    }

    /**
     * Checks whether the authenticated user matches a specific role name.
     */
    public function hasRole(string $role): bool
    {
        return strtolower((string) $this->role()) === strtolower($role);
    }
}
