<?php
declare(strict_types=1);

namespace views;

use function helpers\csrf_token;
use function helpers\e;
use function helpers\pull_session;
use function helpers\pull_flashes;

$flashes = pull_flashes();
$lastUpload = pull_session('last_upload_result');
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
            <h3>Welcome, <?php echo e($email); ?>!</h3>
            <p class="muted">Current role: <strong><?= e((string) $role) ?></strong></p>
            <?php if (!empty($canViewAdminPanel)): ?>
                <p><a href="/admin">Open admin panel</a></p>
            <?php endif; ?>
            <p><a href="/2fa/setup">Manage two-factor authentication</a></p>
            <?php if (!empty($canManageSystem)): ?>
                <form action="/dev/cache/clear" method="post" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="secondary-button">Clear Dev Cache</button>
                </form>
            <?php endif; ?>
            <a href="/logout">logout</a>
        </div>

        <div class="card">
            <h3>Uploads</h3>

            <form action="/uploads/file" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <label for="file">Upload file</label>
                <input type="file" name="file" id="file" required>
                <button type="submit">Upload file</button>
            </form>

            <form action="/uploads/image" method="post" enctype="multipart/form-data" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <label for="image">Upload image</label>
                <input type="file" name="image" id="image" accept="image/*" required>
                <button type="submit">Upload image</button>
            </form>

            <?php if (is_array($lastUpload)): ?>
                <p>Last upload: <a href="<?= e($lastUpload['url']) ?>" target="_blank"><?= e($lastUpload['original_name']) ?></a></p>
                <p class="muted">Stored as <?= e($lastUpload['filename']) ?>, type <?= e($lastUpload['mime_type']) ?></p>
                <?php if (!empty($lastUpload['is_image'])): ?>
                    <div class="qr-preview">
                        <img src="<?= e($lastUpload['url']) ?>" alt="<?= e($lastUpload['original_name']) ?>">
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </body>
    <script src="/assets/js/script.js"></script>
</html>
