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

        <div class="page-shell">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Administration</p>
                    <h1>Admin panel</h1>
                    <p class="muted">Manage access and review account-level activity in one place.</p>
                </div>
                <div class="panel-actions">
                    <a class="secondary-link" href="/dashboard">Back to dashboard</a>
                    <a class="secondary-link" href="/logout">Logout</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="card stat-card">
                    <p class="stat-label">Total users</p>
                    <p class="stat-value"><?= e((string) $stats['total_users']) ?></p>
                </div>
                <div class="card stat-card">
                    <p class="stat-label">Admins</p>
                    <p class="stat-value"><?= e((string) $stats['admins']) ?></p>
                </div>
                <div class="card stat-card">
                    <p class="stat-label">Managers</p>
                    <p class="stat-value"><?= e((string) $stats['managers']) ?></p>
                </div>
                <div class="card stat-card">
                    <p class="stat-label">Users</p>
                    <p class="stat-value"><?= e((string) $stats['users']) ?></p>
                </div>
            </div>

            <?php if ($canManageUsers): ?>
                <div class="card">
                    <div class="section-heading">
                        <div>
                            <h2>Create user</h2>
                            <p class="muted">When this form is submitted from the admin panel, the selected role is saved.</p>
                        </div>
                    </div>

                    <form action="/register" method="post">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="registration_source" value="admin">

                        <label for="new_username">Username</label>
                        <input type="text" name="username" id="new_username" required>

                        <label for="new_email">Email</label>
                        <input type="email" name="email" id="new_email" required>

                        <label for="new_password">Password</label>
                        <input type="password" name="password" id="new_password" required>

                        <label for="new_confirm_password">Confirm Password</label>
                        <input type="password" name="confirm_password" id="new_confirm_password" required>

                        <label for="new_role">Role</label>
                        <select name="role" id="new_role">
                            <?php foreach ($roles as $availableRole): ?>
                                <option value="<?= e($availableRole) ?>"><?= e($availableRole) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit">Create account</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="section-heading">
                    <div>
                        <h2>Access control</h2>
                        <p class="muted">`user` can use the app, `manager` can view this panel, and `admin` can update roles.</p>
                    </div>
                    <span class="role-chip role-<?= e($currentRole) ?>">Signed in as <?= e($currentRole) ?></span>
                </div>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>2FA</th>
                                <th>Joined</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <?php
                                $role = (string) ($user['role'] ?? 'user');
                                $hasEmail2fa = !empty($user['two_factor_email_enabled']);
                                $hasTotp = !empty($user['two_factor_totp_secret']);
                                $twoFactorLabel = $hasEmail2fa && $hasTotp ? 'Email + Authenticator' : ($hasEmail2fa ? 'Email' : ($hasTotp ? 'Authenticator' : 'Off'));
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e((string) $user['username']) ?></strong>
                                        <div class="muted small-text"><?= e((string) $user['email']) ?></div>
                                    </td>
                                    <td><span class="role-chip role-<?= e($role) ?>"><?= e($role) ?></span></td>
                                    <td><?= e($twoFactorLabel) ?></td>
                                    <td><?= e((string) $user['created_at']) ?></td>
                                    <td>
                                        <?php if ($canManageUsers): ?>
                                            <form action="/admin/users/<?= e((string) $user['id']) ?>/role" method="post" class="role-form">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <select name="role" aria-label="Role for <?= e((string) $user['email']) ?>">
                                                    <?php foreach ($roles as $availableRole): ?>
                                                        <option value="<?= e($availableRole) ?>" <?= $availableRole === $role ? 'selected' : '' ?>>
                                                            <?= e($availableRole) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit">Save</button>
                                                <?php if ((int) $user['id'] === $currentUserId): ?>
                                                    <span class="muted small-text">You</span>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted small-text">Read-only access</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </body>
</html>
