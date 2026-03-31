<?php
declare(strict_types=1);

namespace views;

use function helpers\csrf_token;
use function helpers\e;
use function helpers\pull_flashes;

$flashes = pull_flashes();
$isEmail = $method === 'email';
$devEmailCode = $devEmailCode ?? null;
$devTotpPreview = $devTotpPreview ?? null;
?>
<html>
    <head>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
        <?php foreach ($flashes as $flash): ?>
            <p class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
        <?php endforeach; ?>

        <div class="card">
            <h2><?= $isEmail ? 'Email verification' : 'Authenticator verification' ?></h2>
            <p>
                <?= $isEmail
                    ? 'Enter the 6-digit code we sent to your email address.'
                    : 'Open your authenticator app and enter the current 6-digit code.' ?>
            </p>

            <?php if ($isEmail && is_array($devEmailCode)): ?>
                <div class="debug-panel">
                    <h3>Development email code</h3>
                    <p><strong>Current code:</strong> <code><?= e((string) ($devEmailCode['code'] ?? '')) ?></code></p>
                    <p><strong>Expires:</strong> <?= e((string) ($devEmailCode['expires_at'] ?? '')) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$isEmail && is_array($devTotpPreview)): ?>
                <div class="debug-panel">
                    <h3>Development authenticator code</h3>
                    <p><strong>Secret:</strong> <code><?= e((string) ($devTotpPreview['secret'] ?? '')) ?></code></p>
                    <p><strong>Current code:</strong> <code><?= e((string) ($devTotpPreview['current_code'] ?? '')) ?></code></p>
                </div>
            <?php endif; ?>

            <form action="/2fa/verify" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <label for="code"><?= $isEmail ? 'Email code' : 'Authenticator code' ?></label>
                <input type="text" name="code" id="code" inputmode="numeric" autocomplete="one-time-code" required>

                <button type="submit">Verify and sign in</button>
            </form>

            <?php if ($isEmail): ?>
                <form action="/2fa/email/resend" method="post" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="secondary-button">Resend email code</button>
                </form>
            <?php endif; ?>

            <?php if (count($pending['methods']) > 1): ?>
                <p><a href="/2fa/select">Use a different method</a></p>
            <?php endif; ?>
        </div>
    </body>
</html>
