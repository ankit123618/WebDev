<?php
declare(strict_types=1);

namespace core;

use Core\logger;

use function helpers\request_method;
use function helpers\server;
use function helpers\url;

/**
 * Registers routes and dispatches incoming requests to controller actions.
 *
 * It also serves a small set of static assets directly when their files exist.
 */
class router
{
    private array $routes = [];

    /**
     * Stores the service container and logger used during dispatch.
     */
    public function __construct(private container $container, private logger $logger)
    {
    }

    /**
     * Registers a GET route.
     */
    public function get(string $path, string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Registers a POST route.
     */
    public function post(string $path, string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Matches the current request and executes the target controller action.
     */
    public function run(): void
    {
        $requestMethod = request_method();
        $uri = parse_url((string) server('REQUEST_URI', '/'), PHP_URL_PATH);
        $uri = url($uri);

        // Serve static files
        $filePath = __DIR__ . '/../' . $uri;
        if (file_exists($filePath) && is_file($filePath)) {
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
            ];
            if (isset($mimeTypes[$ext])) {
                header('Content-Type: ' . $mimeTypes[$ext]);
                readfile($filePath);
                return;
            }
        }

        $route = $this->matchRoute($requestMethod, $uri);

        if ($route === null) {
            echo "404";
            $this->logger->error("404 Not Found: $requestMethod $uri No route defined");
            return;
        }

        [$handler, $parameters] = $route;

        [$controller, $action] = explode('@', $handler);
        $controllerInstance = $this->container->make($controller);
        $controllerInstance->$action(...array_values($parameters));
    }

    /**
     * Stores a route definition together with metadata about dynamic segments.
     */
    private function addRoute(string $method, string $path, string $handler): void
    {
        $uri = url($path);

        $this->routes[$method][] = [
            'path' => $uri,
            'handler' => $handler,
            'is_dynamic' => str_contains($uri, '{'),
        ];
    }

    /**
     * Finds the first registered route that matches the request method and URI.
     */
    private function matchRoute(string $method, string $uri): ?array
    {
        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $route) {
            if (!$route['is_dynamic'] && $route['path'] === $uri) {
                return [$route['handler'], []];
            }
        }

        foreach ($routes as $route) {
            if (!$route['is_dynamic']) {
                continue;
            }

            $parameters = $this->extractParameters($route['path'], $uri);

            if ($parameters !== null) {
                return [$route['handler'], $parameters];
            }
        }

        return null;
    }

    /**
     * Extracts route parameter values from a URI when a pattern matches.
     */
    private function extractParameters(string $routePath, string $uri): ?array
    {
        $parameterNames = [];
        $segments = explode('/', trim($routePath, '/'));
        $patternSegments = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $matches)) {
                $parameterNames[] = $matches[1];
                $patternSegments[] = '([^/]+)';
                continue;
            }

            $patternSegments[] = preg_quote($segment, '#');
        }

        $pattern = '/' . implode('/', $patternSegments);

        if ($routePath === '/') {
            $pattern = '/';
        }

        if (!preg_match('#^' . $pattern . '$#', $uri, $matches)) {
            return null;
        }

        array_shift($matches);

        if ($parameterNames === []) {
            return [];
        }

        return array_combine($parameterNames, $matches);
    }
}
