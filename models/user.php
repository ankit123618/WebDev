<?php
declare(strict_types=1);

namespace models;

use core\database;
use core\logger;
use PDO;

/**
 * Encapsulates user and password-reset persistence for the application.
 *
 * Every controller-facing database operation for accounts is routed through this model.
 */
class user
{
    /**
     * Stores the shared database connection factory and logger.
     */
    public function __construct(private database $database, private logger $logger)
    {
    }

    /**
     * Returns a user record by email address.
     */
    public function get_user(string $email): array|false
    {
        $db = $this->database->connect();
        $stmt = $db->prepare("SELECT * FROM user WHERE email=?");
        $stmt->execute([$email]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns a user record by primary key.
     */
    public function findById(int|string $id): array|false
    {
        $db = $this->database->connect();
        $stmt = $db->prepare("SELECT * FROM user WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the user list used by the admin panel.
     */
    public function all(): array
    {
        $db = $this->database->connect();
        $stmt = $db->query(
            "SELECT
                id,
                username,
                email,
                role,
                created_at,
                two_factor_email_enabled,
                two_factor_totp_secret
             FROM user
             ORDER BY created_at DESC, id DESC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Updates the stored role for a user.
     */
    public function updateRole(int|string $userId, string $role): bool
    {
        $db = $this->database->connect();
        $stmt = $db->prepare("UPDATE user SET role = ? WHERE id = ?");

        return $stmt->execute([$role, $userId]);
    }

    /**
     * Counts how many users currently belong to a role.
     */
    public function countByRole(string $role): int
    {
        $db = $this->database->connect();
        $stmt = $db->prepare("SELECT COUNT(*) FROM user WHERE role = ?");
        $stmt->execute([$role]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Creates a new user record and returns the insert ID on success.
     *
     * The provided payload should already contain the hashed password value.
     */
    public function save(array $data): bool|string
    {
        $db = $this->database->connect();
        $stmt = $db->prepare(
            "INSERT INTO user (username, email, password, role) VALUES (?, ?, ?, ?)"
        );

        $success = $stmt->execute([
            $data['username'],
            $data['email'],
            $data['password'],
            $data['role'],
        ]);

        if (!$success) {
            $this->logger->error('Database error: ' . implode(' | ', $stmt->errorInfo()));
            return false;
        }

        return $db->lastInsertId();
    }

    /**
     * Stores or replaces the active password reset token for a user.
     */
    public function storePasswordResetToken(int|string $userId, string $tokenHash, string $expiresAt): bool
    {
        $db = $this->database->connect();
        $stmt = $db->prepare(
            "INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)"
        );

        return $stmt->execute([$userId, $tokenHash, $expiresAt]);
    }

    /**
     * Finds an unexpired password reset record by raw token value.
     */
    public function findPasswordResetByToken(string $token): array|false
    {
        $db = $this->database->connect();
        $tokenHash = hash('sha256', $token);

        $stmt = $db->prepare(
            "SELECT user_id, expires_at
             FROM password_resets
             WHERE token_hash = ?
             LIMIT 1"
        );
        $stmt->execute([$tokenHash]);

        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {
            return false;
        }

        if (strtotime($reset['expires_at']) < time()) {
            $this->deletePasswordResetTokensForUser($reset['user_id']);
            return false;
        }

        return $reset;
    }

    /**
     * Replaces the stored password hash for a user.
     */
    public function updatePassword(int|string $userId, string $hashedPassword): bool
    {
        $db = $this->database->connect();
        $stmt = $db->prepare("UPDATE user SET password = ? WHERE id = ?");

        return $stmt->execute([$hashedPassword, $userId]);
    }

    /**
     * Stores the hashed remember-me token and expiration timestamp.
     */
    public function storeRememberMeToken(int|string $userId, string $tokenHash, string $expiresAt): bool
    {
        $db = $this->database->connect();
        $stmt = $db->prepare(
            "UPDATE user
             SET remember_token_hash = ?, remember_token_expires_at = ?
             WHERE id = ?"
        );

        return $stmt->execute([$tokenHash, $expiresAt, $userId]);
    }

    /**
     * Clears the remember-me token for a user.
     */
    public function clearRememberMeToken(int|string $userId): bool
    {
        $db = $this->database->connect();
        $stmt = $db->prepare(
            "UPDATE user
             SET remember_token_hash = NULL, remember_token_expires_at = NULL
             WHERE id = ?"
        );

        return $stmt->execute([$userId]);
    }

    /**
     * Enables or disables email-based two-factor authentication for a user.
     */
    public function updateTwoFactorEmailEnabled(int|string $userId, bool $enabled): bool
    {
        $db = $this->database->connect();
        $stmt = $db->prepare(
            "UPDATE user
             SET two_factor_email_enabled = ?, two_factor_email_code_hash = NULL, two_factor_email_code_expires_at = NULL
             WHERE id = ?"
        );

        return $stmt->execute([$enabled ? 1 : 0, $userId]);
    }

    /**
     * Stores the hashed email verification code and expiration timestamp.
     */
    public function storeTwoFactorEmailCode(int|string $userId, string $hash, string $expiresAt): bool
    {
        $db = $this->database->connect();
        $stmt = $db->prepare(
            "UPDATE user
             SET two_factor_email_code_hash = ?, two_factor_email_code_expires_at = ?
             WHERE id = ?"
        );

        return $stmt->execute([$hash, $expiresAt, $userId]);
    }

    /**
     * Clears the stored email verification code for a user.
     */
    public function clearTwoFactorEmailCode(int|string $userId): bool
    {
        $db = $this->database->connect();
        $stmt = $db->prepare(
            "UPDATE user
             SET two_factor_email_code_hash = NULL, two_factor_email_code_expires_at = NULL
             WHERE id = ?"
        );

        return $stmt->execute([$userId]);
    }

    /**
     * Stores or clears the user's TOTP shared secret.
     */
    public function updateTwoFactorTotpSecret(int|string $userId, ?string $secret): bool
    {
        $db = $this->database->connect();
        $stmt = $db->prepare("UPDATE user SET two_factor_totp_secret = ? WHERE id = ?");

        return $stmt->execute([$secret, $userId]);
    }

    /**
     * Deletes all password reset tokens belonging to a user.
     */
    public function deletePasswordResetTokensForUser(int|string $userId): bool
    {
        $db = $this->database->connect();
        $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");

        return $stmt->execute([$userId]);
    }
}
