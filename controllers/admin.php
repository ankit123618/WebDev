<?php
declare(strict_types=1);

namespace controllers;

use core\acl;
use core\auth;
use core\csrf;
// use core\logger;
use models\user;
use function helpers\flash;
use function helpers\post;
use function helpers\post_string;
use function helpers\redirect;
use function helpers\view;

/**
 * Handles admin-panel pages and role management actions.
 *
 * Access is guarded through the ACL service before any privileged work runs.
 */
class admin
{
    /**
     * Stores the collaborators required for admin authorization and user management.
     */
    public function __construct(
        private auth $auth,
        private acl $acl,
        private user $users,
        private csrf $csrf,
        // private logger $log
    ) {
    }

    /**
     * Ensures the current request belongs to an authenticated user.
     */
    private function ensureAuthenticated(): void
    {
        if ($this->auth->check()) {
            return;
        }

        flash('auth_error', 'Please log in to continue.', 'error');
        redirect('/login');
    }

    /**
     * Ensures the authenticated user has a required permission.
     */
    private function ensurePermission(string $permission): void
    {
        $this->ensureAuthenticated();

        if ($this->acl->can((string) $this->auth->role(), $permission)) {
            return;
        }

        flash('auth_error', 'You do not have permission to access that area.', 'error');
        redirect('/dashboard');
    }

    /**
     * Displays the admin panel overview and user statistics.
     */
    public function index(): void
    {
        $this->ensurePermission('admin.panel.view');

        $users = $this->users->all();

        view('admin', [
            'title' => 'Admin Panel',
            'users' => $users,
            'roles' => $this->acl->roles(),
            'currentUserId' => (int) $this->auth->user(),
            'currentRole' => $this->acl->normalizeRole((string) $this->auth->role()),
            'canManageUsers' => $this->acl->can((string) $this->auth->role(), 'admin.users.manage'),
            'stats' => [
                'total_users' => count($users),
                'admins' => $this->users->countByRole('admin'),
                'managers' => $this->users->countByRole('manager'),
                'users' => $this->users->countByRole('user'),
            ],
        ]);
    }

    /**
     * Updates the role assigned to a specific user account.
     */
    public function updateUserRole(string $id): void
    {
        $this->ensurePermission('admin.users.manage');

        if (!$this->csrf->check((string) post('csrf', ''))) {
            flash('auth_error', 'Invalid request token. Please try again.', 'error');
            redirect('/admin');
        }

        if (!ctype_digit($id)) {
            flash('auth_error', 'Invalid user selected.', 'error');
            redirect('/admin');
        }

        $targetUser = $this->users->findById((int) $id);

        if (!$targetUser) {
            flash('auth_error', 'That user could not be found.', 'error');
            redirect('/admin');
        }

        $role = $this->acl->normalizeRole(post_string('role'));

        if ((int) $targetUser['id'] === (int) $this->auth->user() && $role !== 'admin' && $this->users->countByRole('admin') <= 1) {
            flash('auth_error', 'You cannot remove the last remaining admin.', 'error');
            redirect('/admin');
        }

        if (!$this->users->updateRole((int) $targetUser['id'], $role)) {
            flash('auth_error', 'The role change could not be saved.', 'error');
            redirect('/admin');
        }

        flash('auth_success', 'Role updated for ' . (string) $targetUser['email'] . '.', 'success');
        redirect('/admin');
    }
}
