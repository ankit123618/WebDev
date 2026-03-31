<?php
declare(strict_types=1);

namespace views;

use function helpers\csrf_token;
use function helpers\e;
use function helpers\pull_flashes;

$flashes = pull_flashes();
$token = $token ?? '';
?>

<html>
    <head>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
        <h1>Reset Password</h1>

        <?php foreach ($flashes as $flash): ?>
            <p class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
        <?php endforeach; ?>

        <form action="/reset-password/<?= e($token) ?>" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label for="password">New Password</label>
            <input type="password" name="password" id="password" required>
            <br>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required>
            <br>

            <button type="submit">Update Password</button>
        </form>
    </body>
    <script src="/assets/js/script.js"></script>
</html>
