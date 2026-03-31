<?php
declare(strict_types=1);

namespace core;

use Core\logger;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use models\user;
use PragmaRX\Google2FA\Google2FA;
use function helpers\flash;
use function helpers\forget_session;
use function helpers\session;

/**
 * Coordinates email-code and authenticator-based multi-factor authentication flows.
 *
 * It tracks pending login state, verifies submitted codes, and generates branded QR codes.
 */
class two_factor
{
    private const EMAIL_CODE_TTL = 600;
    private const DEFAULT_LOGO_SIZE = 84;
    private const DEVELOPMENT_EMAIL_CODE = '123456';
    private const TOTP_QR_CACHE_TTL = 600;

    public function __construct(
        private env $env,
        private mailer $mailer,
        private logger $logger,
        private user $users,
        private cache $cache
    ) {
    }

    /**
     * Indicates whether the user has any active second-factor method configured.
     */
    public function hasEnabledMethod(array $user): bool
    {
        return $this->availableMethods($user) !== [];
    }

    /**
     * Returns the enabled verification methods for a user record.
     */
    public function availableMethods(array $user): array
    {
        $methods = [];

        if (!empty($user['two_factor_email_enabled'])) {
            $methods[] = 'email';
        }

        if (!empty($user['two_factor_totp_secret'])) {
            $methods[] = 'totp';
        }

        return $methods;
    }

    /**
     * Stores the minimal state required to continue a pending 2FA login.
     */
    public function beginPendingLogin(array $user): void
    {
        session('pending_2fa_user_id', (int) $user['id']);
        session('pending_2fa_email', (string) $user['email']);
        session('pending_2fa_role', (string) ($user['role'] ?? 'user'));
        session('pending_2fa_methods', $this->availableMethods($user));
        forget_session('pending_2fa_method');
    }

    /**
     * Returns the current pending 2FA login payload from session storage.
     */
    public function getPendingLogin(): ?array
    {
        $userId = session('pending_2fa_user_id');

        if (!$userId) {
            return null;
        }

        return [
            'user_id' => (int) $userId,
            'email' => (string) session('pending_2fa_email'),
            'role' => (string) session('pending_2fa_role'),
            'methods' => session('pending_2fa_methods') ?: [],
            'method' => session('pending_2fa_method'),
        ];
    }

    /**
     * Stores the verification method chosen for the pending login.
     */
    public function setPendingMethod(string $method): void
    {
        session('pending_2fa_method', $method);
    }

    /**
     * Removes all pending 2FA login state from the session.
     */
    public function clearPendingLogin(): void
    {
        forget_session('pending_2fa_user_id');
        forget_session('pending_2fa_email');
        forget_session('pending_2fa_role');
        forget_session('pending_2fa_methods');
        forget_session('pending_2fa_method');
        forget_session('pending_remember_me');
        forget_session('dev_2fa_email_code');
    }

    /**
     * Promotes a successfully verified pending login into the main session.
     */
    public function finalizeLogin(array $user): void
    {
        $this->clearPendingLogin();
        session('user', (int) $user['id']);
        session('role', (string) ($user['role'] ?? 'user'));
        session('email', (string) $user['email']);
    }

    /**
     * Generates, stores, and optionally emails a one-time verification code.
     */
    public function issueEmailCode(array $user): bool
    {
        $code = $this->isDevelopmentMode()
            ? $this->developmentEmailCode()
            : (string) random_int(100000, 999999);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + self::EMAIL_CODE_TTL);

        if (!$this->users->storeTwoFactorEmailCode((int) $user['id'], $hash, $expiresAt)) {
            $this->logger->error('Failed to store 2FA email code for user id: ' . $user['id']);
            return false;
        }

        if ($this->isDevelopmentMode()) {
            session('dev_2fa_email_code', [
                'user_id' => (int) $user['id'],
                'code' => $code,
                'expires_at' => $expiresAt,
            ]);

            flash(
                'auth_success',
                'Development email 2FA code: ' . $code,
                'success'
            );

            return true;
        }

        $body = '<h2>Your verification code</h2>'
            . '<p>Use this code to finish signing in:</p>'
            . '<p style="font-size:28px;font-weight:bold;letter-spacing:4px;">' . $code . '</p>'
            . '<p>This code expires in 10 minutes.</p>';

        if (!$this->mailer->send((string) $user['email'], 'Your sign-in verification code', $body)) {
            $this->logger->error('Failed to send 2FA email code to: ' . $user['email']);

            return false;
        }

        forget_session('dev_2fa_email_code');

        return true;
    }

    /**
     * Verifies a submitted email code against the stored hash or debug preview.
     */
    public function verifyEmailCode(array $user, string $code): bool
    {
        $storedHash = (string) ($user['two_factor_email_code_hash'] ?? '');
        $expiresAt = (string) ($user['two_factor_email_code_expires_at'] ?? '');

        if ($storedHash === '' || $expiresAt === '') {
            return false;
        }

        if (strtotime($expiresAt) < time()) {
            $this->users->clearTwoFactorEmailCode((int) $user['id']);
            return false;
        }

        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';

        if ($normalizedCode === '') {
            return false;
        }

        $valid = password_verify($normalizedCode, $storedHash);

        if ($valid) {
            $this->users->clearTwoFactorEmailCode((int) $user['id']);
            forget_session('dev_2fa_email_code');
            return true;
        }

        $developmentCode = session('dev_2fa_email_code');

        if (
            is_array($developmentCode)
            && (int) ($developmentCode['user_id'] ?? 0) === (int) $user['id']
            && (string) ($developmentCode['code'] ?? '') === $normalizedCode
            && strtotime((string) ($developmentCode['expires_at'] ?? '')) >= time()
        ) {
            $this->users->clearTwoFactorEmailCode((int) $user['id']);
            forget_session('dev_2fa_email_code');
            return true;
        }

        return false;
    }

    /**
     * Generates a new TOTP shared secret.
     */
    public function generateTotpSecret(): string
    {
        return $this->google2fa()->generateSecretKey();
    }

    /**
     * Verifies a TOTP code against the shared secret.
     */
    public function verifyTotpCode(string $secret, string $code): bool
    {
        $normalizedCode = preg_replace('/\s+/', '', trim($code)) ?? '';

        if ($normalizedCode === '') {
            return false;
        }

        return $this->google2fa()->verifyKey($secret, $normalizedCode);
    }

    /**
     * Builds or retrieves a cached QR code data URI for a TOTP secret.
     */
    public function buildTotpQrDataUri(string $email, string $secret): string
    {
        $cacheKey = 'totp_qr:' . hash('sha256', strtolower($email) . '|' . $secret . '|' . $this->totpProvisioningUri($email, $secret));

        return $this->cache->remember($cacheKey, self::TOTP_QR_CACHE_TTL, function () use ($email, $secret): string {
            $writer = new PngWriter();
            $qrCode = new QrCode(
                data: $this->totpProvisioningUri($email, $secret),
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 360,
                margin: 16,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                foregroundColor: new Color(16, 24, 40),
                backgroundColor: new Color(255, 255, 255)
            );

            $logo = new Logo(
                path: $this->defaultLogoPath(),
                resizeToWidth: self::DEFAULT_LOGO_SIZE,
                resizeToHeight: self::DEFAULT_LOGO_SIZE,
                punchoutBackground: true
            );

            return $writer->write($qrCode, $logo)->getDataUri();
        });
    }

    /**
     * Returns the generated default logo path, creating the image when needed.
     */
    public function defaultLogoPath(): string
    {
        $directory = dirname(__DIR__) . '/storage/generated';

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory . '/default-2fa-logo.png';

        if (!file_exists($path)) {
            $appName = trim((string) $this->env->get('APP_NAME', 'APP'));
            $letters = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $appName) ?: 'APP', 0, 2));
            $image = imagecreatetruecolor(160, 160);

            if ($image === false) {
                throw new \RuntimeException('Unable to create default QR logo image.');
            }

            imagealphablending($image, true);
            imagesavealpha($image, true);

            $navy = imagecolorallocate($image, 15, 23, 42);
            $blue = imagecolorallocate($image, 29, 78, 216);
            $white = imagecolorallocate($image, 255, 255, 255);
            $lightBlue = imagecolorallocate($image, 191, 219, 254);

            imagefilledrectangle($image, 0, 0, 159, 159, $navy);
            imagefilledrectangle($image, 14, 14, 145, 145, $blue);
            imagefilledrectangle($image, 49, 95, 111, 107, $lightBlue);
            imagestring($image, 5, 64, 112, $letters, $white);

            imagepng($image, $path);
            imagedestroy($image);
        }

        return $path;
    }

    /**
     * Builds the provisioning URI consumed by authenticator applications.
     */
    public function totpProvisioningUri(string $email, string $secret): string
    {
        $appName = trim((string) $this->env->get('APP_NAME', 'Project Template'));

        return $this->google2fa()->getQRCodeUrl($appName, $email, $secret);
    }

    /**
     * Creates the shared Google2FA instance used across TOTP operations.
     */
    private function google2fa(): Google2FA
    {
        $google2fa = new Google2FA();
        $google2fa->setWindow(1);

        return $google2fa;
    }

    /**
     * Indicates whether development shortcuts should be enabled for 2FA.
     */
    private function isDevelopmentMode(): bool
    {
        $appEnv = strtolower(trim((string) $this->env->get('APP_ENV', 'production')));
        $appDebug = strtolower(trim((string) $this->env->get('APP_DEBUG', 'false')));

        return $appEnv === 'development' || in_array($appDebug, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Returns the development email code override or the built-in fallback.
     */
    private function developmentEmailCode(): string
    {
        $configured = preg_replace('/\D+/', '', (string) $this->env->get('DEV_2FA_EMAIL_CODE', '')) ?? '';

        if (strlen($configured) === 6) {
            return $configured;
        }

        return self::DEVELOPMENT_EMAIL_CODE;
    }
}
