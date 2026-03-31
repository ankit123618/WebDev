<?php
declare(strict_types=1);

namespace core;

use PragmaRX\Google2FA\Google2FA;
use function helpers\forget_session;
use function helpers\session;

/**
 * Exposes development-only authentication previews for reset links and 2FA codes.
 *
 * This keeps debug conveniences isolated from normal production behavior.
 */
class debug_auth
{
    /**
     * Receives environment configuration used to detect debug mode.
     */
    public function __construct(private env $env)
    {
    }

    /**
     * Indicates whether authentication debug helpers are enabled.
     */
    public function isEnabled(): bool
    {
        $appDebug = strtolower(trim((string) $this->env->get('APP_DEBUG', 'false')));

        return in_array($appDebug, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Stores a password reset preview in session for development workflows.
     */
    public function rememberPasswordResetLink(string $email, string $link, string $expiresAt): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        session('dev_password_reset_preview', [
            'email' => $email,
            'link' => $link,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Returns the saved password reset preview, optionally filtering by email.
     */
    public function passwordResetPreview(?string $email = null): ?array
    {
        $preview = session('dev_password_reset_preview');

        if (!is_array($preview)) {
            return null;
        }

        if ($email !== null && strcasecmp((string) ($preview['email'] ?? ''), $email) !== 0) {
            return null;
        }

        return $preview;
    }

    /**
     * Removes the stored password reset preview from the session.
     */
    public function clearPasswordResetPreview(): void
    {
        forget_session('dev_password_reset_preview');
    }

    /**
     * Returns the current development email-code preview if one exists.
     */
    public function emailCodePreview(): ?array
    {
        $preview = session('dev_2fa_email_code');

        return is_array($preview) ? $preview : null;
    }

    /**
     * Builds a development preview payload for authenticator-based 2FA.
     */
    public function totpPreview(string $email, string $secret): ?array
    {
        if (!$this->isEnabled() || $secret === '') {
            return null;
        }

        return [
            'email' => $email,
            'secret' => $secret,
            'provisioning_uri' => $this->google2fa()->getQRCodeUrl(
                trim((string) $this->env->get('APP_NAME', 'Project Template')),
                $email,
                $secret
            ),
            'current_code' => str_pad((string) $this->google2fa()->getCurrentOtp($secret), 6, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Creates a Google2FA instance with the window used across the app.
     */
    private function google2fa(): Google2FA
    {
        $google2fa = new Google2FA();
        $google2fa->setWindow(1);

        return $google2fa;
    }
}
