<?php

declare(strict_types=1);

namespace FP\Resv\Core\Helpers;

use FP\Resv\Kernel\Bootstrap;
use FP\Resv\Kernel\LegacyBridge;

/**
 * Container Helper
 * 
 * Provides convenient helper functions to access services from the container.
 * These functions can be used throughout the plugin for easy service access.
 *
 * @package FP\Resv\Core\Helpers
 */
final class ContainerHelper
{
    /**
     * Get a service from the container
     * 
     * @template T
     * @param class-string<T>|string $id Service identifier
     * @return T|mixed
     */
    public static function get(string $id)
    {
        try {
            return Bootstrap::container()->get($id);
        } catch (\RuntimeException $e) {
            // Fallback to LegacyBridge if Bootstrap not initialized
            return LegacyBridge::get($id);
        }
    }
    
    /**
     * Get logger service
     * 
     * @return \FP\Resv\Core\Services\LoggerInterface
     */
    public static function logger(): \FP\Resv\Core\Services\LoggerInterface
    {
        return self::get(\FP\Resv\Core\Services\LoggerInterface::class);
    }
    
    /**
     * Get cache service
     * 
     * @return \FP\Resv\Core\Services\CacheInterface
     */
    public static function cache(): \FP\Resv\Core\Services\CacheInterface
    {
        return self::get(\FP\Resv\Core\Services\CacheInterface::class);
    }
    
    /**
     * Get options service
     * 
     * @return \FP\Resv\Core\Services\OptionsInterface
     */
    public static function options(): \FP\Resv\Core\Services\OptionsInterface
    {
        return self::get(\FP\Resv\Core\Services\OptionsInterface::class);
    }
    
    /**
     * Get validator service
     * 
     * @return \FP\Resv\Core\Services\ValidatorInterface
     */
    public static function validator(): \FP\Resv\Core\Services\ValidatorInterface
    {
        return self::get(\FP\Resv\Core\Services\ValidatorInterface::class);
    }
    
    /**
     * Get sanitizer service
     * 
     * @return \FP\Resv\Core\Services\SanitizerInterface
     */
    public static function sanitizer(): \FP\Resv\Core\Services\SanitizerInterface
    {
        return self::get(\FP\Resv\Core\Services\SanitizerInterface::class);
    }
    
    /**
     * Get HTTP client service
     * 
     * @return \FP\Resv\Core\Services\HttpClientInterface
     */
    public static function http(): \FP\Resv\Core\Services\HttpClientInterface
    {
        return self::get(\FP\Resv\Core\Services\HttpClientInterface::class);
    }
}










