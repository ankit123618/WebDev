<?php
declare(strict_types=1);

namespace core;
/**
 * Generates and validates the single session-based CSRF token used by forms.
 *
 * The token is lazily created and then compared with constant-time checks.
 */
class csrf {

    /**
     * Returns the active CSRF token, creating one when needed.
     */
    public function generate(): string
    {
        if(!isset($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    /**
     * Validates a submitted token against the current session token.
     */
    public function check(string $token): bool
    {
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

}
