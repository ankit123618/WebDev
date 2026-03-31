<?php
declare(strict_types=1);

namespace views;

use function helpers\csrf_token;
use function helpers\e;
use function helpers\pull_flashes;

$flashes = pull_flashes();
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
            <h2>Choose verification method</h2>
            <p>Finish signing in for <?= e($pending['email']) ?>.</p>

            <form action="/2fa/select" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <label class="choice">
                    <input type="radio" name="method" value="email" required>
                    <span>Email code</span>
                </label>

                <label class="choice">
                    <input type="radio" name="method" value="totp" required>
                    <span>Authenticator app</span>
                </label>

                <button type="submit">Continue</button>
            </form>
        </div>
    </body>
</html>
