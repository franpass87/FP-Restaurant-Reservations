<?php

declare(strict_types=1);

namespace FP\Resv\Kernel;

/**
 * Legacy Bridge
 * 
 * Provides backward compatibility with existing code during migration.
 * Allows gradual migration without breaking existing functionality.
 *
 * @package FP\Resv\Kernel
 */
final class LegacyBridge
{
    private static ?Container $container = null;
    
    /**
     * Initialize bridge with container
     * 
     * @param Container $container Service container
     * @return void
     */
    public static function init(Container $container): void
    {
        self::$container = $container;
        
        // Make container accessible globally for legacy code
        // This allows existing code to access new services during migration
        if (!defined('FP_RESV_CONTAINER')) {
            define('FP_RESV_CONTAINER', true);
        }
    }
    
    /**
     * Get container instance
     * 
     * @return Container
     * @throws \RuntimeException If container not initialized
     */
    public static function container(): Container
    {
        if (self::$container === null) {
            throw new \RuntimeException('LegacyBridge not initialized. Call init() first.');
        }
        
        return self::$container;
    }
    
    /**
     * Get a service (for legacy code compatibility)
     * 
     * @param string $id Service identifier
     * @return mixed
     */
    public static function get(string $id)
    {
        return self::container()->get($id);
    }
    
    /**
     * Check if service exists
     * 
     * @param string $id Service identifier
     * @return bool
     */
    public static function has(string $id): bool
    {
        return self::container()->has($id);
    }
    
    /**
     * Get container instance (compatible with both new and legacy systems)
     * 
     * This method provides backward compatibility by trying the new container first,
     * then falling back to the legacy ServiceContainer if needed.
     * 
     * @return \FP\Resv\Kernel\Container|\FP\Resv\Core\ServiceContainer
     */
    public static function getContainer()
    {
        // Try new container first
        try {
            return self::container();
        } catch (\RuntimeException $e) {
            // Fallback to legacy container for backward compatibility
            if (class_exists('\FP\Resv\Core\ServiceContainer')) {
                return \FP\Resv\Core\ServiceContainer::getInstance();
            }
            throw $e;
        }
    }
}










