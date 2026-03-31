<?php
declare(strict_types=1);

namespace controllers;

use core\acl;
use core\auth;
use function helpers\flash;
use function helpers\redirect;
use function helpers\view;

/**
 * Renders the authenticated user dashboard.
 *
 * It prepares the current user's identity and authorization flags for the view.
 */
class dashboard
{
    /**
     * Stores the authentication and authorization services used by the dashboard.
     */
    public function __construct(private auth $auth, private acl $acl)
    {
    }

    /**
     * Displays the dashboard for an authenticated user.
     */
    public function index(): void
    {
        if (!$this->auth->check()) {
            flash('auth_error', 'Please log in to continue.', 'error');
            redirect('/login');
        }

        view('dashboard', [
            'title' => 'Dashboard',
            'userId' => $this->auth->user(),
            'email' => $this->auth->email(),
            'role' => $this->auth->role(),
            'canViewAdminPanel' => $this->acl->can((string) $this->auth->role(), 'admin.panel.view'),
            'canManageSystem' => $this->acl->can((string) $this->auth->role(), 'admin.system.manage'),
        ]);
    }
}
