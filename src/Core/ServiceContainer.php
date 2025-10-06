<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class ServiceContainer
{
    /**
     * @var self|null
     */
    private static $instance = null;

    /** @var array<string, mixed> */
    private $services = [];

    /** @var array<string, callable> */
    private $factories = [];

    /** @var array<string, bool> */
    private $shared = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a concrete service instance.
     */
    public function register(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
        $this->shared[$id] = true;
    }

    /**
     * Register a factory callable for lazy instantiation.
     * 
     * @param callable $factory Function that receives the container and returns the service
     * @param bool $shared Whether to cache the instance after first creation (default: true)
     */
    public function factory(string $id, callable $factory, bool $shared = true): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = $shared;
    }

    /**
     * Register a singleton factory (always shared).
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->factory($id, $factory, true);
    }

    /**
     * Register a transient factory (never shared, creates new instance each time).
     */
    public function transient(string $id, callable $factory): void
    {
        $this->factory($id, $factory, false);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services) || array_key_exists($id, $this->factories);
    }

    public function get(string $id, mixed $default = null): mixed
    {
        // Return existing instance if available
        if (array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }

        // Create from factory if registered
        if (array_key_exists($id, $this->factories)) {
            $instance = $this->factories[$id]($this);

            // Cache if shared
            if ($this->shared[$id] ?? true) {
                $this->services[$id] = $instance;
            }

            return $instance;
        }

        return $default;
    }

    /**
     * Extend an existing service with a decorator.
     * 
     * @param callable $decorator Function that receives the original service and container, returns decorated service
     */
    public function extend(string $id, callable $decorator): void
    {
        if (!$this->has($id)) {
            throw new \RuntimeException(sprintf('Service "%s" not found in container', $id));
        }

        $original = $this->get($id);
        $extended = $decorator($original, $this);

        $this->services[$id] = $extended;
    }

    /**
     * Remove a service from the container.
     */
    public function remove(string $id): void
    {
        unset($this->services[$id], $this->factories[$id], $this->shared[$id]);
    }

    /**
     * Get all registered service IDs.
     * 
     * @return string[]
     */
    public function getServiceIds(): array
    {
        return array_unique(array_merge(
            array_keys($this->services),
            array_keys($this->factories)
        ));
    }
}
