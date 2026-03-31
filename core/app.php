<?php
declare(strict_types=1);

namespace core;

/**
 * Exposes the application service container through a simple static accessor.
 *
 * This allows helper functions and bootstrap code to resolve shared services.
 */
class app
{
    private static ?container $container = null;

    /**
     * Stores the application container instance for global access.
     */
    public static function setContainer(container $container): void
    {
        self::$container = $container;
    }

    /**
     * Returns the configured application container.
     */
    public static function container(): container
    {
        if (self::$container === null) {
            throw new \RuntimeException('Application container has not been initialized.');
        }

        return self::$container;
    }
}
