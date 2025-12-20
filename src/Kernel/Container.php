<?php

declare(strict_types=1);

namespace FP\Resv\Kernel;

use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * PSR-11 Compatible Service Container
 * 
 * Provides dependency injection with:
 * - PSR-11 compliance
 * - Constructor injection
 * - Singleton support
 * - Factory support
 * - Service aliases
 * - Lazy loading
 *
 * @package FP\Resv\Kernel
 */
final class Container implements ContainerInterface
{
    /** @var array<string, callable|object|string> Service bindings */
    private array $bindings = [];
    
    /** @var array<string, object> Singleton instances */
    private array $instances = [];
    
    /** @var array<string, bool> Singleton tracking */
    private array $singletons = [];
    
    /** @var array<string, string> Service aliases */
    private array $aliases = [];
    
    /**
     * Bind a service to the container
     * 
     * @param string $id Service identifier (typically class name)
     * @param callable|object|string $concrete Service factory, instance, or class name
     * @param bool $singleton Whether to treat as singleton
     */
    public function bind(string $id, $concrete, bool $singleton = false): void
    {
        $this->bindings[$id] = $concrete;
        
        if ($singleton) {
            $this->singletons[$id] = true;
        }
    }
    
    /**
     * Bind a service as singleton
     * 
     * @param string $id Service identifier
     * @param callable|object|string $concrete Service factory, instance, or class name
     */
    public function singleton(string $id, $concrete): void
    {
        $this->bind($id, $concrete, true);
    }
    
    /**
     * Bind a service only if not already bound
     * 
     * @param string $id Service identifier
     * @param callable|object|string $concrete Service factory, instance, or class name
     * @param bool $singleton Whether to treat as singleton
     */
    public function bindIf(string $id, $concrete, bool $singleton = false): void
    {
        if (!$this->has($id)) {
            $this->bind($id, $concrete, $singleton);
        }
    }
    
    /**
     * Register a service alias
     * 
     * @param string $alias Alias name
     * @param string $id Service identifier
     */
    public function alias(string $alias, string $id): void
    {
        $this->aliases[$alias] = $id;
    }
    
    /**
     * Get a service from the container (PSR-11)
     * 
     * @template T
     * @param class-string<T>|string $id Service identifier
     * @return T|mixed
     * @throws RuntimeException If service not found
     */
    public function get(string $id)
    {
        // Resolve alias if present
        $id = $this->resolveAlias($id);
        
        // If singleton and already resolved, return cached instance
        if (isset($this->singletons[$id]) && isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        
        if (!array_key_exists($id, $this->bindings)) {
            // Try to auto-resolve if it's a class name
            if (class_exists($id)) {
                return $this->resolve($id);
            }
            
            throw new RuntimeException(sprintf('Service "%s" not found in container.', $id));
        }
        
        $concrete = $this->bindings[$id];
        
        // If it's already an object and not a factory, return it
        if (is_object($concrete) && !is_callable($concrete)) {
            $instance = $concrete;
        } elseif (is_callable($concrete)) {
            // Call factory with container for dependency injection
            $instance = $concrete($this);
        } elseif (is_string($concrete) && class_exists($concrete)) {
            // Resolve class name
            $instance = $this->resolve($concrete);
        } else {
            throw new RuntimeException(sprintf('Invalid binding for service "%s".', $id));
        }
        
        // Cache singleton instances
        if (isset($this->singletons[$id])) {
            $this->instances[$id] = $instance;
        }
        
        return $instance;
    }
    
    /**
     * Check if service exists (PSR-11)
     * 
     * @param string $id Service identifier
     * @return bool
     */
    public function has(string $id): bool
    {
        $id = $this->resolveAlias($id);
        
        if (array_key_exists($id, $this->bindings)) {
            return true;
        }
        
        // Check if it's a class that can be auto-resolved
        return class_exists($id);
    }
    
    /**
     * Resolve a class by instantiating it with dependency injection
     * 
     * @param string $class Class name
     * @return object
     * @throws RuntimeException If class cannot be instantiated
     */
    private function resolve(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException(sprintf('Class "%s" does not exist.', $class));
        }
        
        $reflection = new \ReflectionClass($class);
        
        // If no constructor, instantiate directly
        if (!$reflection->hasMethod('__construct')) {
            return $reflection->newInstance();
        }
        
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();
        
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if (!$type || $type instanceof \ReflectionUnionType) {
                // No type hint or union type - try default value
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new RuntimeException(
                        sprintf(
                            'Cannot resolve parameter "%s" for class "%s".',
                            $parameter->getName(),
                            $class
                        )
                    );
                }
                continue;
            }
            
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencyClass = $type->getName();
                
                // Try to get from container first
                if ($this->has($dependencyClass)) {
                    $dependencies[] = $this->get($dependencyClass);
                } elseif (class_exists($dependencyClass)) {
                    // Recursively resolve
                    $dependencies[] = $this->resolve($dependencyClass);
                } else {
                    // Try default value
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new RuntimeException(
                            sprintf(
                                'Cannot resolve dependency "%s" for class "%s".',
                                $dependencyClass,
                                $class
                            )
                        );
                    }
                }
            } else {
                // Built-in type - try default value
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new RuntimeException(
                        sprintf(
                            'Cannot resolve built-in parameter "%s" for class "%s".',
                            $parameter->getName(),
                            $class
                        )
                    );
                }
            }
        }
        
        return $reflection->newInstanceArgs($dependencies);
    }
    
    /**
     * Resolve service alias
     * 
     * @param string $id Service identifier
     * @return string Resolved identifier
     */
    private function resolveAlias(string $id): string
    {
        return $this->aliases[$id] ?? $id;
    }
    
    /**
     * Clear all bindings (useful for testing)
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->singletons = [];
        $this->aliases = [];
    }
}










