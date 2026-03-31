<?php
declare(strict_types=1);

namespace controllers;

use core\auth as auth_manager;
use core\acl;
use core\csrf;
use core\cache;
use core\debug_auth;
use core\env;
use core\mailer;
use core\remember_me;
use core\two_factor;
use Core\logger;
use models\user;
use PDOException;
use Throwable;
use function helpers\flash;
use function helpers\forget_session;
use function helpers\has_session;
use function helpers\post;
use function helpers\has_post;
use function helpers\post_string;
use function helpers\redirect;
use function helpers\server;
use function helpers\session;
use function helpers\view;

/**
 * Coordinates login, registration, logout, password reset, and MFA flows.
 *
 * It brings together persistence, messaging, authorization, and session services.
 */
class auth
{
    /**
     * Stores the services required across authentication and account-security actions.
     */
    public function __construct(
        private user $users,
        private mailer $mailer,
        private csrf $csrf,
        private cache $cache,
        private acl $acl,
        private debug_auth $debugAuth,
        private auth_manager $auth,
        private remember_me $rememberMe,
        private env $env,
        private logger $logger,
        private two_factor $twoFactor
    ) {
    }

    /**
     * Returns the configured application base URL for links sent to users.
     */
    private function baseUrl(): string
    {
        $configured = trim((string) $this->env->get('APP_URL', ''));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $https = server('HTTPS', '');
        $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';
        $host = server('HTTP_HOST', 'localhost');

        return $scheme . '://' . $host;
    }

    /**
     * Returns the authenticated user record or redirects when the session is invalid.
     */
    private function authenticatedUser(): array
    {
        $userId = $this->auth->user();

        if (!$userId) {
            flash('auth_error', 'Please log in to continue.', 'error');
            redirect('/login');
        }

        $user = $this->users->findById((int) $userId);

        if (!$user) {
            session_destroy();
            flash('auth_error', 'Your session is no longer valid. Please log in again.', 'error');
            redirect('/login');
        }

        return $user;
    }

    /**
     * Returns the user record tied to the pending 2FA login state.
     */
    private function pendingLoginUser(): array
    {
        $pending = $this->twoFactor->getPendingLogin();

        if ($pending === null) {
            flash('auth_error', 'Your verification session has expired. Please sign in again.', 'error');
            redirect('/login');
        }

        $user = $this->users->findById($pending['user_id']);

        if (!$user) {
            $this->twoFactor->clearPendingLogin();
            flash('auth_error', 'Unable to complete sign in. Please try again.', 'error');
            redirect('/login');
        }

        return $user;
    }

    /**
     * Verifies a CSRF token and redirects with an error when it is invalid.
     */
    private function requireValidCsrf(string $value, string $message, string $redirectTo): void
    {
        if ($this->csrf->check($value)) {
            return;
        }

        $this->logger->error('CSRF token mismatch for route: ' . (string) server('REQUEST_URI', 'unknown'));
        flash('auth_error', $message, 'error');
        redirect($redirectTo);
    }

    /**
     * Clears the temporary TOTP secret used during setup confirmation.
     */
    private function clearPendingTotpSetup(): void
    {
        forget_session('pending_totp_secret');
    }

    /**
     * Indicates whether the login form requested a persistent session.
     */
    private function wantsRememberMe(): bool
    {
        return has_post('remember_me');
    }

    /**
     * Indicates whether development-only authentication tools should be enabled.
     */
    private function isDevelopmentMode(): bool
    {
        $appEnv = strtolower(trim((string) $this->env->get('APP_ENV', 'production')));
        $appDebug = strtolower(trim((string) $this->env->get('APP_DEBUG', 'false')));

        return $appEnv === 'development' || in_array($appDebug, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Chooses the default destination after a successful login.
     */
    private function postLoginRedirect(): string
    {
        if ($this->acl->can((string) $this->auth->role(), 'admin.panel.view')) {
            return '/admin';
        }

        return '/dashboard';
    }

    /**
     * Displays the login form or redirects authenticated users away from it.
     */
    public function login(): void
    {
        if ($this->auth->check() || has_session('user')) {
            redirect($this->postLoginRedirect());
        }

        view('login');
    }

    /**
     * Validates credentials and starts the login or 2FA flow.
     */
    public function authenticate(): void
    {
        $this->requireValidCsrf(
            (string) post('csrf', ''),
            'Your session expired. Please try logging in again.',
            '/login'
        );

        $email = post_string('email');
        $password = (string) post('password', '');

        $user = $this->users->get_user($email);

        if (!$user || !password_verify($password, (string) $user['password'])) {
            $this->logger->error('Failed login attempt for email: ' . $email);
            flash('auth_error', 'Invalid email or password.', 'error');
            redirect('/login');
        }

        if (!$this->twoFactor->hasEnabledMethod($user)) {
            $this->twoFactor->finalizeLogin($user);

            if ($this->wantsRememberMe()) {
                $this->rememberMe->issue($user);
            }

            flash('auth_success', 'You are now logged in.', 'success');
            redirect($this->postLoginRedirect());
        }

        $methods = $this->twoFactor->availableMethods($user);
        $this->twoFactor->beginPendingLogin($user);
        session('pending_remember_me', $this->wantsRememberMe());

        if (count($methods) === 1) {
            $method = $methods[0];
            $this->twoFactor->setPendingMethod($method);

            if ($method === 'email' && !$this->twoFactor->issueEmailCode($user)) {
                $this->twoFactor->clearPendingLogin();
                flash('auth_error', 'We could not send your email verification code. Please try again.', 'error');
                redirect('/login');
            }

            redirect('/2fa/verify');
        }

        redirect('/2fa/select');
    }

    /**
     * Displays the verification-method picker for pending 2FA logins.
     */
    public function selectTwoFactorMethod(): void
    {
        $pending = $this->twoFactor->getPendingLogin();

        if ($pending === null) {
            flash('auth_error', 'Please sign in to continue.', 'error');
            redirect('/login');
        }

        view('two-factor-select', ['pending' => $pending]);
    }

    /**
     * Saves the chosen verification method for the pending 2FA login.
     */
    public function chooseTwoFactorMethod(): void
    {
        $this->requireValidCsrf(
            (string) post('csrf', ''),
            'Your verification session expired. Please sign in again.',
            '/login'
        );

        $user = $this->pendingLoginUser();
        $methods = $this->twoFactor->availableMethods($user);
        $method = post_string('method');

        if (!in_array($method, $methods, true)) {
            flash('auth_error', 'Please choose a valid verification method.', 'error');
            redirect('/2fa/select');
        }

        $this->twoFactor->setPendingMethod($method);

        if ($method === 'email' && !$this->twoFactor->issueEmailCode($user)) {
            $this->twoFactor->clearPendingLogin();
            flash('auth_error', 'We could not send your email verification code. Please try signing in again.', 'error');
            redirect('/login');
        }

        redirect('/2fa/verify');
    }

    /**
     * Displays the verification challenge for the pending 2FA method.
     */
    public function twoFactorChallenge(): void
    {
        $pending = $this->twoFactor->getPendingLogin();

        if ($pending === null) {
            flash('auth_error', 'Please sign in to continue.', 'error');
            redirect('/login');
        }

        $method = (string) ($pending['method'] ?? '');

        if ($method === '') {
            if (count($pending['methods']) > 1) {
                redirect('/2fa/select');
            }

            $method = $pending['methods'][0] ?? '';
            $this->twoFactor->setPendingMethod($method);
        }

        if ($method === '') {
            $this->twoFactor->clearPendingLogin();
            flash('auth_error', 'No verification method is available for this sign in.', 'error');
            redirect('/login');
        }

        $user = $this->pendingLoginUser();
        $devEmailCode = $method === 'email' ? $this->debugAuth->emailCodePreview() : null;
        $devTotpPreview = $method === 'totp'
            ? $this->debugAuth->totpPreview((string) $user['email'], (string) ($user['two_factor_totp_secret'] ?? ''))
            : null;

        view('two-factor-verify', [
            'pending' => $pending,
            'method' => $method,
            'devEmailCode' => $devEmailCode,
            'devTotpPreview' => $devTotpPreview,
        ]);
    }

    /**
     * Verifies the submitted 2FA code and completes the login flow.
     */
    public function verifyTwoFactorChallenge(): void
    {
        $this->requireValidCsrf(
            (string) post('csrf', ''),
            'Your verification session expired. Please sign in again.',
            '/login'
        );

        $user = $this->pendingLoginUser();
        $pending = $this->twoFactor->getPendingLogin();
        $method = (string) ($pending['method'] ?? '');
        $code = post_string('code');

        if ($method === 'email') {
            if (!$this->twoFactor->verifyEmailCode($user, $code)) {
                flash('auth_error', 'That email verification code is invalid or expired.', 'error');
                redirect('/2fa/verify');
            }
        } elseif ($method === 'totp') {
            $secret = (string) ($user['two_factor_totp_secret'] ?? '');

            if ($secret === '' || !$this->twoFactor->verifyTotpCode($secret, $code)) {
                flash('auth_error', 'That authenticator code is invalid. Please try again.', 'error');
                redirect('/2fa/verify');
            }
        } else {
            $this->twoFactor->clearPendingLogin();
            flash('auth_error', 'Your verification session is invalid. Please sign in again.', 'error');
            redirect('/login');
        }

        $this->twoFactor->finalizeLogin($user);

        if (session('pending_remember_me')) {
            $this->rememberMe->issue($user);
        }

        forget_session('pending_remember_me');
        flash('auth_success', 'You are now logged in.', 'success');
        redirect($this->postLoginRedirect());
    }

    /**
     * Sends a new email verification code for the pending 2FA login.
     */
    public function resendEmailCode(): void
    {
        $this->requireValidCsrf(
            (string) post('csrf', ''),
            'Your verification session expired. Please sign in again.',
            '/login'
        );

        $user = $this->pendingLoginUser();
        $methods = $this->twoFactor->availableMethods($user);

        if (!in_array('email', $methods, true)) {
            flash('auth_error', 'Email verification is not enabled for this account.', 'error');
            redirect('/2fa/select');
        }

        $this->twoFactor->setPendingMethod('email');

        if (!$this->twoFactor->issueEmailCode($user)) {
            flash('auth_error', 'We could not resend your email verification code right now.', 'error');
            redirect('/2fa/verify');
        }

        flash('auth_success', 'A fresh email verification code has been sent.', 'success');
        redirect('/2fa/verify');
    }

    /**
     * Displays the registration form.
     */
    public function register(): void
    {
        view('register');
    }

    /**
     * Creates a new user account from the submitted registration form.
     */
    public function store(): void
    {
        if (!$this->csrf->check((string) post('csrf', ''))) {
            $this->logger->error('Invalid CSRF token on registration attempt');
            die('Invalid CSRF token');
        }

        $registrationSource = post_string('registration_source', 'public');
        $isAdminRegistration = $registrationSource === 'admin';
        $redirectTo = $isAdminRegistration ? '/admin' : '/register';

        if ($isAdminRegistration && !$this->acl->can((string) $this->auth->role(), 'admin.users.manage')) {
            $this->logger->error('Unauthorized admin registration attempt', [
                'registration_source' => $registrationSource,
                'email' => post_string('email'),
            ]);
            flash('auth_error', 'You do not have permission to create privileged accounts.', 'error');
            redirect('/dashboard');
        }

        $username = post_string('username');
        $email = post_string('email');
        $password = (string) post('password', '');
        $confirmPassword = (string) post('confirm_password', '');

        if ($username === '' || $email === '' || $password === '') {
            flash('register_error', 'Please complete all required fields.', 'error');
            redirect($redirectTo);
        }

        if ($password !== $confirmPassword) {
            $this->logger->error('Password mismatch during registration for email: ' . $email);
            flash('register_error', 'Passwords do not match.', 'error');
            redirect($redirectTo);
        }

        if ($this->users->get_user($email)) {
            flash('register_error', 'An account with that email already exists.', 'error');
            redirect($redirectTo);
        }

        $role = $isAdminRegistration
            ? $this->acl->normalizeRole(post_string('role', 'user'))
            : 'user';

        $data = [
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ];

        try {
            $id = $this->users->save($data);
        } catch (PDOException $e) {
            $this->logger->exception($e, 'Registration database error', [
                'email' => $email,
            ]);

            $message = strtolower($e->getMessage());

            if ((string) $e->getCode() === '23000' || str_contains($message, 'duplicate entry')) {
                flash('register_error', 'An account with that email already exists.', 'error');
                redirect($redirectTo);
            }

            if (str_contains($message, 'base table or view not found') || str_contains($message, 'unknown column')) {
                flash('register_error', 'Registration is unavailable until the database schema is updated.', 'error');
                redirect($redirectTo);
            }

            flash('register_error', 'We could not create your account right now. Please try again later.', 'error');
            redirect($redirectTo);
        } catch (Throwable $e) {
            $this->logger->exception($e, 'Registration failed', [
                'email' => $email,
            ]);
            flash('register_error', 'We could not create your account right now. Please try again later.', 'error');
            redirect($redirectTo);
        }

        if (!$id) {
            $this->logger->error('Failed to create user with email: ' . $data['email']);
            flash('register_error', 'We could not create your account right now. Please try again later.', 'error');
            redirect($redirectTo);
        }

        $this->logger->info('Registration succeeded', [
            'email' => $email,
            'registration_source' => $registrationSource,
            'saved_role' => $role,
            'created_user_id' => (string) $id,
        ]);

        if ($isAdminRegistration) {
            flash('auth_success', 'User account created successfully.', 'success');
            redirect('/admin');
        }

        flash('auth_success', 'Registration successful. Please log in.', 'success');
        redirect('/login');
    }

    /**
     * Displays the 2FA settings page for the authenticated user.
     */
    public function twoFactorSettings(): void
    {
        $user = $this->authenticatedUser();
        $pendingSecret = (string) session('pending_totp_secret');
        $qrCodeDataUri = null;

        if ($pendingSecret !== '') {
            $qrCodeDataUri = $this->twoFactor->buildTotpQrDataUri((string) $user['email'], $pendingSecret);
        }

        view('two-factor-settings', [
            'user' => $user,
            'pendingSecret' => $pendingSecret,
            'qrCodeDataUri' => $qrCodeDataUri,
            'devTotpPreview' => $this->debugAuth->totpPreview((string) $user['email'], $pendingSecret),
            'debugAuthEnabled' => $this->debugAuth->isEnabled(),
        ]);
    }

    /**
     * Enables email-based 2FA for the authenticated user.
     */
    public function enableEmailTwoFactor(): void
    {
        $this->requireValidCsrf((string) post('csrf', ''), 'Invalid request.', '/2fa/setup');
        $user = $this->authenticatedUser();

        if (!$this->users->updateTwoFactorEmailEnabled((int) $user['id'], true)) {
            flash('auth_error', 'Could not enable email verification.', 'error');
            redirect('/2fa/setup');
        }

        flash('auth_success', 'Email verification is now enabled for sign-in.', 'success');
        redirect('/2fa/setup');
    }

    /**
     * Disables email-based 2FA for the authenticated user.
     */
    public function disableEmailTwoFactor(): void
    {
        $this->requireValidCsrf((string) post('csrf', ''), 'Invalid request.', '/2fa/setup');
        $user = $this->authenticatedUser();

        if (!$this->users->updateTwoFactorEmailEnabled((int) $user['id'], false)) {
            flash('auth_error', 'Could not disable email verification.', 'error');
            redirect('/2fa/setup');
        }

        flash('auth_success', 'Email verification has been turned off.', 'success');
        redirect('/2fa/setup');
    }

    /**
     * Starts TOTP setup by generating a temporary shared secret.
     */
    public function prepareTotpSetup(): void
    {
        $this->requireValidCsrf((string) post('csrf', ''), 'Invalid request.', '/2fa/setup');
        $this->authenticatedUser();
        session('pending_totp_secret', $this->twoFactor->generateTotpSecret());

        flash('auth_success', 'Scan the QR code in your authenticator app, then enter the 6-digit code to finish setup.', 'success');
        redirect('/2fa/setup');
    }

    /**
     * Confirms TOTP setup using the temporary secret and submitted code.
     */
    public function confirmTotpSetup(): void
    {
        $this->requireValidCsrf((string) post('csrf', ''), 'Invalid request.', '/2fa/setup');
        $user = $this->authenticatedUser();
        $pendingSecret = (string) session('pending_totp_secret');

        if ($pendingSecret === '') {
            flash('auth_error', 'Generate a QR code before confirming authenticator setup.', 'error');
            redirect('/2fa/setup');
        }

        if (!$this->twoFactor->verifyTotpCode($pendingSecret, post_string('code'))) {
            flash('auth_error', 'That authenticator code is invalid. Try the latest code from your app.', 'error');
            redirect('/2fa/setup');
        }

        if (!$this->users->updateTwoFactorTotpSecret((int) $user['id'], $pendingSecret)) {
            flash('auth_error', 'Could not save authenticator app setup.', 'error');
            redirect('/2fa/setup');
        }

        $this->clearPendingTotpSetup();
        flash('auth_success', 'Authenticator app verification is now enabled.', 'success');
        redirect('/2fa/setup');
    }

    /**
     * Disables authenticator-app 2FA for the authenticated user.
     */
    public function disableTotp(): void
    {
        $this->requireValidCsrf((string) post('csrf', ''), 'Invalid request.', '/2fa/setup');
        $user = $this->authenticatedUser();

        if (!$this->users->updateTwoFactorTotpSecret((int) $user['id'], null)) {
            flash('auth_error', 'Could not disable authenticator verification.', 'error');
            redirect('/2fa/setup');
        }

        $this->clearPendingTotpSetup();
        flash('auth_success', 'Authenticator app verification has been turned off.', 'success');
        redirect('/2fa/setup');
    }

    /**
     * Clears development cache entries for authorized users.
     */
    public function clearCache(): void
    {
        $this->requireValidCsrf((string) post('csrf', ''), 'Invalid request.', '/dashboard');
        $this->authenticatedUser();

        if (!$this->acl->can((string) $this->auth->role(), 'admin.system.manage')) {
            flash('auth_error', 'You do not have permission to clear the cache.', 'error');
            redirect('/dashboard');
        }

        if (!$this->isDevelopmentMode()) {
            flash('auth_error', 'Cache clearing is only available in development.', 'error');
            redirect('/dashboard');
        }

        $this->cache->flush();
        flash('auth_success', 'Development cache cleared.', 'success');
        redirect('/dashboard');
    }

    /**
     * Logs the current user out and clears remember-me state.
     */
    public function logout(): void
    {
        $this->rememberMe->clearForCurrentUser();
        session_destroy();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        flash('auth_success', 'You have been logged out.', 'success');
        redirect('/login');
    }

    /**
     * Displays the password reset request form.
     */
    public function forgotPassword(): void
    {
        view('forgot-password', [
            'resetPreview' => $this->debugAuth->passwordResetPreview(),
            'debugAuthEnabled' => $this->debugAuth->isEnabled(),
        ]);
    }

    /**
     * Generates and emails a password reset link when the account exists.
     */
    public function sendResetLink(): void
    {
        if (!$this->csrf->check((string) post('csrf', ''))) {
            $this->logger->error('CSRF token mismatch on forgot password request');
            flash('forgot_error', 'Invalid request. Please try again.', 'error');
            redirect('/forgot-password');
        }

        $email = post_string('email');

        if ($email === '') {
            flash('forgot_error', 'Please enter your email address.', 'error');
            redirect('/forgot-password');
        }

        $user = $this->users->get_user($email);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $stored = $this->users->storePasswordResetToken((int) $user['id'], $tokenHash, $expiresAt);

            if ($stored) {
                $resetLink = $this->baseUrl() . '/reset-password/' . $token;
                $this->debugAuth->rememberPasswordResetLink($email, $resetLink, $expiresAt);
                $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
                $body = '<h2>Password Reset</h2>'
                    . '<p>We received a request to reset your password.</p>'
                    . '<p><a href="' . $safeLink . '">Click here to reset your password</a></p>'
                    . '<p>This link will expire in 1 hour.</p>';

                if (!$this->mailer->send($email, 'Reset your password', $body)) {
                    $this->logger->error('Failed to send reset email to: ' . $email);
                }
            } else {
                $this->logger->error('Failed to store reset token for: ' . $email);
            }
        } else {
            $this->debugAuth->clearPasswordResetPreview();
        }

        flash('forgot_success', 'If the email exists, a reset link has been sent.', 'success');
        redirect('/forgot-password');
    }

    /**
     * Displays the password reset form for a valid token.
     */
    public function resetPasswordForm(string $token): void
    {
        $reset = $this->users->findPasswordResetByToken($token);

        if (!$reset) {
            flash('forgot_error', 'This reset link is invalid or expired.', 'error');
            redirect('/forgot-password');
        }

        view('reset-password', ['token' => $token]);
    }

    /**
     * Verifies the reset token and stores the new password hash.
     */
    public function resetPassword(string $token): void
    {
        if (!$this->csrf->check((string) post('csrf', ''))) {
            $this->logger->error('CSRF token mismatch on reset password request');
            flash('forgot_error', 'Invalid request. Please try again.', 'error');
            redirect('/forgot-password');
        }

        $reset = $this->users->findPasswordResetByToken($token);

        if (!$reset) {
            flash('forgot_error', 'This reset link is invalid or expired.', 'error');
            redirect('/forgot-password');
        }

        $password = (string) post('password', '');
        $confirmPassword = (string) post('confirm_password', '');

        if ($password === '' || $confirmPassword === '') {
            flash('reset_error', 'Please fill in both password fields.', 'error');
            redirect('/reset-password/' . $token);
        }

        if ($password !== $confirmPassword) {
            flash('reset_error', 'Passwords do not match.', 'error');
            redirect('/reset-password/' . $token);
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        if (!$this->users->updatePassword((int) $reset['user_id'], $hashedPassword)) {
            $this->logger->error('Failed to update password for user id: ' . $reset['user_id']);
            flash('reset_error', 'Could not update password. Please try again.', 'error');
            redirect('/reset-password/' . $token);
        }

        $this->users->deletePasswordResetTokensForUser((int) $reset['user_id']);

        flash('auth_success', 'Password updated successfully. Please log in.', 'success');
        redirect('/login');
    }
}
