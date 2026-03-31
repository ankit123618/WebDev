<?php
declare(strict_types=1);

namespace controllers;

use core\auth;
use core\csrf;
use core\uploader;
use Core\logger;
use function helpers\flash;
use function helpers\post;
use function helpers\redirect;
use function helpers\session;

/**
 * Processes authenticated file uploads and serves stored upload assets.
 *
 * Validation, storage, and lookup are delegated to the uploader service.
 */
class upload
{
    /**
     * Stores the services used to authorize, validate, and log upload actions.
     */
    public function __construct(
        private auth $auth,
        private csrf $csrf,
        private uploader $uploader,
        private logger $logger
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
     * Verifies the CSRF token for upload form submissions.
     */
    private function ensureValidCsrf(): void
    {
        if ($this->csrf->check((string) post('csrf', ''))) {
            return;
        }

        flash('auth_error', 'Invalid request. Please try again.', 'error');
        redirect('/dashboard');
    }

    /**
     * Stores a generic uploaded file and remembers the result in session.
     */
    public function uploadFile(): void
    {
        $this->ensureAuthenticated();
        $this->ensureValidCsrf();

        try {
            $result = $this->uploader->store($_FILES['file'] ?? []);
        } catch (\Throwable $e) {
            $this->logger->exception($e, 'File upload failed');
            flash('auth_error', $e->getMessage(), 'error');
            redirect('/dashboard');
        }

        session('last_upload_result', $result);
        flash('auth_success', 'File uploaded successfully.', 'success');
        redirect('/dashboard');
    }

    /**
     * Stores an uploaded image and remembers the result in session.
     */
    public function uploadImage(): void
    {
        $this->ensureAuthenticated();
        $this->ensureValidCsrf();

        try {
            $result = $this->uploader->storeImage($_FILES['image'] ?? []);
        } catch (\Throwable $e) {
            $this->logger->exception($e, 'Image upload failed');
            flash('auth_error', $e->getMessage(), 'error');
            redirect('/dashboard');
        }

        session('last_upload_result', $result);
        flash('auth_success', 'Image uploaded successfully.', 'success');
        redirect('/dashboard');
    }

    /**
     * Streams a stored upload back to the browser when it exists.
     */
    public function show(string $disk, string $filename): void
    {
        try {
            $file = $this->uploader->locate($disk, $filename);
        } catch (\Throwable $e) {
            $this->logger->exception($e, 'Uploaded file lookup failed', [
                'disk' => $disk,
                'filename' => $filename,
            ]);
            http_response_code(404);
            echo 'File not found';
            return;
        }

        if ($file === null) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . (string) $file['size']);
        header('Content-Disposition: inline; filename="' . $file['filename'] . '"');
        readfile($file['path']);
    }
}
