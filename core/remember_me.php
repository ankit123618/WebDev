<?php
declare(strict_types=1);

namespace core;

use Core\logger;
use models\user;
use function helpers\cookie_delete;
use function helpers\cookie_get;
use function helpers\cookie_set;
use function helpers\session;

/**
 * Manages persistent login cookies and automatic session restoration.
 *
 * Tokens are stored hashed in the database and rotated after successful cookie login.
 */
class remember_me
{
    private const COOKIE_NAME = 'remember_me';
    private const COOKIE_TTL = 2592000;

    /**
     * Stores the collaborators needed for token persistence and auditing.
     */
    public function __construct(private user $users, private logger $logger)
    {
    }

    /**
     * Issues a new remember-me token for a user and persists its hash.
     */
    public function issue(array $user): void
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::COOKIE_TTL);

        if (!$this->users->storeRememberMeToken((int) $user['id'], $tokenHash, $expiresAt)) {
            $this->logger->error('Failed to store remember-me token for user id: ' . $user['id']);
            return;
        }

        cookie_set(self::COOKIE_NAME, $user['id'] . ':' . $token, time() + self::COOKIE_TTL);
    }

    /**
     * Clears the remember-me token for the currently authenticated user.
     */
    public function clearForCurrentUser(): void
    {
        $userId = session('user');

        if ($userId) {
            $this->users->clearRememberMeToken((int) $userId);
        }

        cookie_delete(self::COOKIE_NAME);
    }

    /**
     * Clears the remember-me token for a specific user ID.
     */
    public function clearForUserId(int $userId): void
    {
        $this->users->clearRememberMeToken($userId);
        cookie_delete(self::COOKIE_NAME);
    }

    /**
     * Attempts to restore a user session from the remember-me cookie.
     */
    public function attemptAutoLogin(): void
    {
        if (session('user')) {
            return;
        }

        $cookieValue = cookie_get(self::COOKIE_NAME);

        if ($cookieValue === null || !str_contains($cookieValue, ':')) {
            return;
        }

        [$userId, $token] = explode(':', $cookieValue, 2);

        if (!ctype_digit($userId) || $token === '') {
            cookie_delete(self::COOKIE_NAME);
            return;
        }

        $user = $this->users->findById((int) $userId);

        if (!$user) {
            cookie_delete(self::COOKIE_NAME);
            return;
        }

        $storedHash = (string) ($user['remember_token_hash'] ?? '');
        $expiresAt = (string) ($user['remember_token_expires_at'] ?? '');

        if ($storedHash === '' || $expiresAt === '' || strtotime($expiresAt) < time()) {
            $this->clearForUserId((int) $userId);
            return;
        }

        if (!hash_equals($storedHash, hash('sha256', $token))) {
            $this->logger->error('Remember-me token mismatch for user id: ' . $userId);
            $this->clearForUserId((int) $userId);
            return;
        }

        session('user', (int) $user['id']);
        session('role', (string) ($user['role'] ?? 'user'));
        session('email', (string) $user['email']);

        // Rotate the token on every successful cookie login.
        $this->issue($user);
    }
}
