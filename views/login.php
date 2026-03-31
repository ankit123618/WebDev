<?php
declare(strict_types=1);

namespace views;
/** Login Page */
// start the session so CSRF::generate() can store a token
use function helpers\csrf_token;
use function helpers\e;
use function helpers\pull_flashes;

$flashes = pull_flashes();
?>
<!--
    The form should post to the authentication route defined in `routes/web.php`.
    The controller expects an email address, so label/field reflect that.
-->

<html>
    <head>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
        <?php foreach ($flashes as $flash): ?>
            <p class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
        <?php endforeach; ?>

        <form action="/login" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required>
            <br>
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
            <br>
            <label class="choice">
                <input type="checkbox" name="remember_me" value="1">
                <span>Remember me on this device</span>
            </label>
            <button type="submit">Login</button>
        </form>

        <p><a href="/forgot-password">Forgot your password?</a></p>
        <p><a href="/register">Need an account? Register</a></p>
    </body>
    <script src="/assets/js/script.js"></script>
</html>
