<?php
declare(strict_types=1);

/** Registration Page */
namespace views;
use function helpers\csrf_token;
use function helpers\e;
use function helpers\old_or;
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

        <form action="/register" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        
            <label for="username">Username</label>
            <input type="text" name="username" id="username" value="<?= e(old_or('username')) ?>" required>
            <br>
        
            <label for="email">Email</label>
            <input type="email" name="email" id="email" value="<?= e(old_or('email')) ?>" required>
            <br>
        
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
            <br>
            <label for="confirm_password">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required>
        
            <button type="submit">Register</button>
        </form>
    </body>
    <script src="/assets/js/script.js"></script>
</html>
