<?php
declare(strict_types=1);

namespace controllers;

use function helpers\view;

/**
 * Renders the public landing page.
 *
 * This controller keeps the home route intentionally small and view-focused.
 */
class home
{
    /**
     * Displays the home page view.
     */
    public function index(): void
    {
        view('home', [
            'title' => 'Home Page'
        ]);
    }
}
