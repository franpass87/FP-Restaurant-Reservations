<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class ServiceContainer
{
    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $services = [];

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

    public function register(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }

    public function get(string $id, mixed $default = null): mixed
    {
        return $this->services[$id] ?? $default;
    }
}
