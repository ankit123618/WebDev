<?php
declare(strict_types=1);

namespace core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Provides lightweight dependency injection and shared service resolution.
 *
 * It supports singleton bindings, transient bindings, and constructor autowiring.
 */
class container
{
    private array $bindings = [];
    private array $instances = [];

    /**
     * Registers a shared binding that will be resolved once and reused.
     */
    public function singleton(string $id, callable|string|object $concrete): void
    {
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'shared' => true,
        ];
    }

    /**
     * Registers a non-shared binding that is resolved on demand.
     */
    public function bind(string $id, callable|string|object $concrete): void
    {
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'shared' => false,
        ];
    }

    /**
     * Resolves a binding, shared instance, or autowirable class name.
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (array_key_exists($id, $this->bindings)) {
            $binding = $this->bindings[$id];
            $resolved = $this->resolve($binding['concrete']);

            if ($binding['shared']) {
                $this->instances[$id] = $resolved;
            }

            return $resolved;
        }

        if (class_exists($id)) {
            return $this->build($id);
        }

        throw new RuntimeException("No binding found for [{$id}].");
    }

    /**
     * Resolves a binding and guarantees that the result is an object instance.
     */
    public function make(string $id): object
    {
        $resolved = $this->get($id);

        if (!is_object($resolved)) {
            throw new RuntimeException("Resolved entry [{$id}] is not an object.");
        }

        return $resolved;
    }

    /**
     * Resolves a binding target from a closure, class name, or raw object.
     */
    private function resolve(callable|string|object $concrete): mixed
    {
        if ($concrete instanceof Closure || is_callable($concrete)) {
            return $concrete($this);
        }

        if (is_string($concrete)) {
            return $this->build($concrete);
        }

        return $concrete;
    }

    /**
     * Instantiates a class by resolving its constructor dependencies recursively.
     */
    private function build(string $class): object
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class [{$class}] is not instantiable.");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new RuntimeException(
                    "Unable to resolve parameter [{$parameter->getName()}] for class [{$class}]."
                );
            }

            $dependencies[] = $this->get($type->getName());
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
