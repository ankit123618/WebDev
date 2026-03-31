<?php
declare(strict_types=1);

namespace views;

use function helpers\csrf_token;
use function helpers\e;
use function helpers\pull_flashes;

$flashes = pull_flashes();
$emailEnabled = !empty($user['two_factor_email_enabled']);
$totpEnabled = !empty($user['two_factor_totp_secret']);
$devTotpPreview = $devTotpPreview ?? null;
$debugAuthEnabled = $debugAuthEnabled ?? false;
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
            <h2>Two-factor authentication</h2>
            <p>Manage sign-in verification for <?= e($user['email']) ?>.</p>
            <p><a href="/dashboard">Back to dashboard</a></p>
        </div>

        <div class="card">
            <h3>Email code</h3>
            <p>Status: <?= $emailEnabled ? 'Enabled' : 'Disabled' ?></p>

            <form action="<?= $emailEnabled ? '/2fa/email/disable' : '/2fa/email/enable' ?>" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit"><?= $emailEnabled ? 'Disable email code' : 'Enable email code' ?></button>
            </form>
        </div>

        <div class="card">
            <h3>Authenticator app (TOTP)</h3>
            <p>Status: <?= $totpEnabled ? 'Enabled' : 'Disabled' ?></p>
            <p>The QR code uses the generated default logo.</p>

            <form action="/2fa/totp/setup" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit"><?= $pendingSecret !== '' ? 'Regenerate QR' : 'Generate QR for authenticator app' ?></button>
            </form>

            <?php if ($qrCodeDataUri !== null): ?>
                <div class="qr-preview">
                    <img src="<?= e($qrCodeDataUri) ?>" alt="Authenticator setup QR code">
                </div>

                <?php if ($debugAuthEnabled && is_array($devTotpPreview)): ?>
                    <div class="debug-panel">
                        <h4>Development authenticator preview</h4>
                        <p>Use the secret below for manual entry, or use the current code directly while debugging.</p>
                        <p><strong>Secret:</strong> <code><?= e((string) ($devTotpPreview['secret'] ?? '')) ?></code></p>
                        <p><strong>Current code:</strong> <code><?= e((string) ($devTotpPreview['current_code'] ?? '')) ?></code></p>

                        <label for="dev-provisioning-uri">Provisioning URI</label>
                        <input
                            type="text"
                            id="dev-provisioning-uri"
                            readonly
                            value="<?= e((string) ($devTotpPreview['provisioning_uri'] ?? '')) ?>"
                        >
                    </div>
                <?php endif; ?>

                <form action="/2fa/totp/confirm" method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <label for="code">Authenticator code</label>
                    <input type="text" name="code" id="code" inputmode="numeric" autocomplete="one-time-code" required>

                    <button type="submit">Enable authenticator app</button>
                </form>
            <?php endif; ?>

            <?php if ($totpEnabled): ?>
                <form action="/2fa/totp/disable" method="post" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="danger-button">Disable authenticator app</button>
                </form>
            <?php endif; ?>
        </div>
    </body>
</html>
