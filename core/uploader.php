<?php
declare(strict_types=1);

namespace core;

use RuntimeException;

/**
 * Validates uploaded files, stores them on disk, and resolves stored assets later.
 *
 * The service supports generic files and a stricter image-only upload flow.
 */
class uploader
{
    private const DEFAULT_ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/json',
        'application/zip',
    ];

    private const IMAGE_ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    /**
     * Stores the environment service used to resolve upload directories.
     */
    public function __construct(private env $env)
    {
    }

    /**
     * Validates and stores an uploaded file using the supplied storage options.
     */
    public function store(array $file, array $options = []): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || (!is_uploaded_file($tmpName) && !is_file($tmpName))) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $mimeType = mime_content_type($tmpName) ?: 'application/octet-stream';
        $size = (int) ($file['size'] ?? filesize($tmpName) ?: 0);
        $originalName = (string) ($file['name'] ?? 'upload');
        $disk = $this->sanitizeSegment((string) ($options['disk'] ?? 'files'));
        $allowedMimeTypes = $options['allowed_mime_types'] ?? self::DEFAULT_ALLOWED_MIME_TYPES;
        $maxBytes = (int) ($options['max_bytes'] ?? 10 * 1024 * 1024);

        if ($maxBytes > 0 && $size > $maxBytes) {
            throw new RuntimeException('Uploaded file is too large.');
        }

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('This file type is not allowed.');
        }

        $extension = $this->detectExtension($mimeType, $originalName);
        $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . ($extension !== '' ? '.' . $extension : '');
        $directory = $this->diskPath($disk);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $targetPath = $directory . '/' . $filename;

        if (!move_uploaded_file($tmpName, $targetPath) && !rename($tmpName, $targetPath)) {
            throw new RuntimeException('Unable to save uploaded file.');
        }

        return [
            'disk' => $disk,
            'filename' => $filename,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size' => $size,
            'path' => $targetPath,
            'url' => '/uploads/' . rawurlencode($disk) . '/' . rawurlencode($filename),
            'is_image' => str_starts_with($mimeType, 'image/'),
        ];
    }

    /**
     * Stores an uploaded image using image-specific defaults.
     */
    public function storeImage(array $file, array $options = []): array
    {
        $options['disk'] = $options['disk'] ?? 'images';
        $options['allowed_mime_types'] = $options['allowed_mime_types'] ?? self::IMAGE_ALLOWED_MIME_TYPES;
        $options['max_bytes'] = $options['max_bytes'] ?? 5 * 1024 * 1024;

        return $this->store($file, $options);
    }

    /**
     * Returns metadata for a previously stored upload when it exists.
     */
    public function locate(string $disk, string $filename): ?array
    {
        $safeDisk = $this->sanitizeSegment($disk);
        $safeFilename = $this->sanitizeFilename($filename);
        $path = $this->diskPath($safeDisk) . '/' . $safeFilename;

        if (!is_file($path)) {
            return null;
        }

        $mimeType = mime_content_type($path) ?: 'application/octet-stream';

        return [
            'disk' => $safeDisk,
            'filename' => $safeFilename,
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => filesize($path) ?: 0,
            'is_image' => str_starts_with($mimeType, 'image/'),
        ];
    }

    /**
     * Returns the absolute storage directory for the given upload disk.
     */
    private function diskPath(string $disk): string
    {
        $configured = trim((string) $this->env->get('UPLOAD_DIR', 'storage/uploads'));

        return dirname(__DIR__) . '/' . trim($configured, '/') . '/' . $disk;
    }

    /**
     * Determines the final file extension from MIME type or original name.
     */
    private function detectExtension(string $mimeType, string $originalName): string
    {
        $map = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/json' => 'json',
            'application/zip' => 'zip',
        ];

        if (isset($map[$mimeType])) {
            return $map[$mimeType];
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return preg_replace('/[^a-z0-9]+/', '', $extension) ?? '';
    }

    /**
     * Sanitizes a directory segment used for upload disk names.
     */
    private function sanitizeSegment(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]+/', '', $value) ?? '';

        if ($sanitized === '') {
            throw new RuntimeException('Invalid upload target.');
        }

        return $sanitized;
    }

    /**
     * Sanitizes a filename before it is used in file-system lookups.
     */
    private function sanitizeFilename(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]+/', '', basename($value)) ?? '';

        if ($sanitized === '') {
            throw new RuntimeException('Invalid filename.');
        }

        return $sanitized;
    }

    /**
     * Converts a PHP upload error code into a user-facing message.
     */
    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the allowed size.',
            UPLOAD_ERR_PARTIAL => 'Uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'Please choose a file to upload.',
            default => 'File upload failed.',
        };
    }
}
