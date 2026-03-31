<?php
declare(strict_types=1);

namespace views;

use function helpers\csrf_token;
use function helpers\e;
use function helpers\pull_flashes;

$flashes = pull_flashes();
$resetPreview = $resetPreview ?? null;
$debugAuthEnabled = $debugAuthEnabled ?? false;
?>

<html>
    <head>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
        <h1>Forgot Password</h1>

        <?php foreach ($flashes as $flash): ?>
            <p class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
        <?php endforeach; ?>

        <form action="/forgot-password" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label for="email">Email</label>
            <input type="email" name="email" id="email" required>
            <br>

            <button type="submit">Send Reset Link</button>
        </form>

        <?php if ($debugAuthEnabled && is_array($resetPreview)): ?>
            <div class="card debug-panel">
                <h2>Development reset preview</h2>
                <p>Reset email delivery is bypassed while debug mode is enabled.</p>
                <p><strong>Email:</strong> <?= e((string) ($resetPreview['email'] ?? '')) ?></p>
                <p><strong>Expires:</strong> <?= e((string) ($resetPreview['expires_at'] ?? '')) ?></p>

                <label for="dev-reset-link">Reset link</label>
                <input
                    type="text"
                    id="dev-reset-link"
                    readonly
                    value="<?= e((string) ($resetPreview['link'] ?? '')) ?>"
                >
            </div>
        <?php endif; ?>

        <p><a href="/login">Back to login</a></p>
    </body>
    <script src="/assets/js/script.js"></script>
</html>
