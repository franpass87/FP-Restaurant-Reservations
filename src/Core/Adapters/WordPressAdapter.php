<?php

declare(strict_types=1);

namespace FP\Resv\Core\Adapters;

interface WordPressAdapter
{
    public function getCurrentTime(string $type = 'mysql'): string;
    
    public function getSalt(string $scheme): string;
    
    public function getTransient(string $key): mixed;
    
    public function setTransient(string $key, mixed $value, int $expiration): bool;
    
    public function deleteTransient(string $key): bool;
    
    public function cacheGet(string $key, string $group = ''): mixed;
    
    public function cacheSet(string $key, mixed $value, string $group = '', int $expiration = 0): bool;
    
    public function cacheDelete(string $key, string $group = ''): bool;
    
    public function cacheIncr(string $key, int $offset = 1, string $group = ''): int|false;
    
    public function getOption(string $option, mixed $default = false): mixed;
    
    public function updateOption(string $option, mixed $value, bool $autoload = null): bool;
    
    public function addOption(string $option, mixed $value, string $deprecated = '', bool $autoload = true): bool;
    
    public function deleteOption(string $option): bool;
    
    public function doAction(string $hook_name, mixed ...$args): void;
    
    public function applyFilters(string $hook_name, mixed $value, mixed ...$args): mixed;
}
