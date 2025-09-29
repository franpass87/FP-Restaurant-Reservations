<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        if (str_starts_with($class, 'FP\\Resv\\')) {
            $relative = substr($class, strlen('FP\\Resv\\'));
            $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
            $path     = Plugin::$dir . 'src/' . $relative . '.php';

            if (is_readable($path)) {
                require_once $path;
            }
        }
    }
}
