<?php

declare(strict_types=1);

namespace FP\Resv\Providers;

use FP\Resv\Kernel\Container;

/**
 * Core Service Provider
 * 
 * Registers core services and adapters that are used throughout the plugin.
 * Also conditionally registers feature providers based on context.
 *
 * @package FP\Resv\Providers
 */
final class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register core services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function register(Container $container): void
    {
        // Register plugin file in container for easy access
        $container->singleton('plugin.file', fn() => \FP\Resv\Kernel\Bootstrap::pluginFile());
        
        // Register core services
        $container->singleton(\FP\Resv\Core\Services\LoggerInterface::class, \FP\Resv\Core\Services\Logger::class);
        $container->singleton(\FP\Resv\Core\Services\CacheInterface::class, \FP\Resv\Core\Services\Cache::class);
        $container->singleton(\FP\Resv\Core\Services\OptionsInterface::class, \FP\Resv\Core\Services\Options::class);
        $container->singleton(\FP\Resv\Core\Services\ValidatorInterface::class, \FP\Resv\Core\Services\Validator::class);
        $container->singleton(\FP\Resv\Core\Services\SanitizerInterface::class, \FP\Resv\Core\Services\Sanitizer::class);
        $container->singleton(\FP\Resv\Core\Services\HttpClientInterface::class, \FP\Resv\Core\Services\HttpClient::class);
        
        // Register adapters
        $container->singleton(\FP\Resv\Core\Adapters\WordPressAdapterInterface::class, \FP\Resv\Core\Adapters\WordPressAdapter::class);
        $container->singleton(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class, \FP\Resv\Core\Adapters\DatabaseAdapter::class);
        $container->singleton(\FP\Resv\Core\Adapters\HooksAdapterInterface::class, \FP\Resv\Core\Adapters\HooksAdapter::class);
        
        // Register legacy adapter alias
        $container->singleton('wp.adapter', function (Container $container) {
            return new \FP\Resv\Core\Adapters\WPFunctionsAdapter();
        });
        
        // Register core mailer
        $container->singleton(
            \FP\Resv\Core\Mailer::class,
            function (Container $container) {
                $mailer = new \FP\Resv\Core\Mailer();
                $mailer->registerHooks();
                return $mailer;
            }
        );
        
        $container->alias('core.mailer', \FP\Resv\Core\Mailer::class);
        
        // Register async mailer
        $container->singleton(
            \FP\Resv\Core\AsyncMailer::class,
            function (Container $container) {
                $mailer = $container->get(\FP\Resv\Core\Mailer::class);
                $asyncMailer = new \FP\Resv\Core\AsyncMailer($mailer);
                $asyncMailer->boot();
                return $asyncMailer;
            }
        );
        
        $container->alias('async.mailer', \FP\Resv\Core\AsyncMailer::class);
        
        // Initialize Consent and Security (static initialization)
        $this->initializeStaticServices($container);
        
        // Note: Privacy is registered in BusinessServiceProvider because it depends on repositories
        
        // Register aliases for convenience
        $container->alias('logger', \FP\Resv\Core\Services\LoggerInterface::class);
        $container->alias('cache', \FP\Resv\Core\Services\CacheInterface::class);
        $container->alias('options', \FP\Resv\Core\Services\OptionsInterface::class);
        $container->alias('validator', \FP\Resv\Core\Services\ValidatorInterface::class);
        $container->alias('sanitizer', \FP\Resv\Core\Services\SanitizerInterface::class);
        $container->alias('http', \FP\Resv\Core\Services\HttpClientInterface::class);
        $container->alias('wp', \FP\Resv\Core\Adapters\WordPressAdapterInterface::class);
        $container->alias('db', \FP\Resv\Core\Adapters\DatabaseAdapterInterface::class);
        $container->alias('hooks', \FP\Resv\Core\Adapters\HooksAdapterInterface::class);
        
        // Conditionally register feature providers based on context
        $this->registerFeatureProviders($container);
    }
    
    /**
     * Initialize static services that need early initialization
     */
    private function initializeStaticServices(Container $container): void
    {
        $options = $container->get(\FP\Resv\Core\Services\OptionsInterface::class);
        
        // Initialize Consent (static method)
        \FP\Resv\Core\Consent::init($options);
        
        // Boot Security (static method)
        \FP\Resv\Core\Security::boot();
    }
    
    /**
     * Boot core services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Boot feature providers
        $this->bootFeatureProviders($container);
        
        // Boot business services that need initialization
        if ($container->has('feature.providers')) {
            /** @var ServiceProvider[] $providers */
            $providers = $container->get('feature.providers');
            foreach ($providers as $provider) {
                if ($provider instanceof BusinessServiceProvider) {
                    $provider->boot($container);
                }
            }
        }
    }
    
    /**
     * Register feature providers based on context
     * 
     * @param Container $container Service container
     * @return void
     */
    private function registerFeatureProviders(Container $container): void
    {
        // Store providers to boot later
        $providers = [];
        
        // Always register data provider
        $dataProvider = new DataServiceProvider();
        $dataProvider->register($container);
        $providers[] = $dataProvider;
        
        // Always register business provider (domain services)
        $businessProvider = new BusinessServiceProvider();
        $businessProvider->register($container);
        $providers[] = $businessProvider;
        
        // Always register integration provider
        $integrationProvider = new IntegrationServiceProvider();
        $integrationProvider->register($container);
        $providers[] = $integrationProvider;
        
        // Register based on context
        if (is_admin()) {
            $adminProvider = new AdminServiceProvider();
            $adminProvider->register($container);
            $providers[] = $adminProvider;
        }
        
        if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            $frontendProvider = new FrontendServiceProvider();
            $frontendProvider->register($container);
            $providers[] = $frontendProvider;
        }
        
        // Always register REST provider (it will only register routes on rest_api_init)
        // This ensures endpoints are available even if REST_REQUEST is not set early
        $restProvider = new RESTServiceProvider();
        $restProvider->register($container);
        $providers[] = $restProvider;
        
        if (defined('WP_CLI') && WP_CLI) {
            $cliProvider = new CLIServiceProvider();
            $cliProvider->register($container);
            $providers[] = $cliProvider;
        }
        
        // Store for booting
        $container->bind('feature.providers', fn() => $providers);
    }
    
    /**
     * Boot feature providers
     * 
     * @param Container $container Service container
     * @return void
     */
    private function bootFeatureProviders(Container $container): void
    {
        if (!$container->has('feature.providers')) {
            return;
        }
        
        /** @var ServiceProvider[] $providers */
        $providers = $container->get('feature.providers');
        
        foreach ($providers as $provider) {
            $provider->boot($container);
        }
    }
}
