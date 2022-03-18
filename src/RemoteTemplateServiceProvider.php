<?php

namespace Schnoop\RemoteTemplate;

use GuzzleHttp\Client;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\ViewFinderInterface;
use Illuminate\View\ViewServiceProvider;
use Schnoop\RemoteTemplate\View\Factory;
use Schnoop\RemoteTemplate\View\FileViewFinder;
use Schnoop\RemoteTemplate\View\RemoteTemplateFinder;

class RemoteTemplateServiceProvider extends ViewServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/remote-view.php' => config_path('remote-view.php'),
            ], 'config');
        }
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->registerViewFinder();
        $this->registerFactory();
        $this->registerBladeCompiler();
        $this->registerEngineResolver();
    }

    /**
     * Register the view finder implementation.
     */
    public function registerViewFinder(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/remote-view.php', 'remote-view');

        $this->app->singleton('remoteview.finder', function ($app) {
            return new RemoteTemplateFinder(
                $app['files'],
                $app['config'],
                new Client($app['config']['remote-view.guzzle-config']),
            );
        });

        $this->app->bind(
            'view.finder',
            fn ($app) => new FileViewFinder(
                $app['files'],
                $app['config']['view.paths'],
                $app['remoteview.finder'],
                null,
            ),
        );
    }

    /**
     * Create a new Factory Instance.
     *
     * @param EngineResolver $resolver
     * @param ViewFinderInterface $finder
     * @param Dispatcher $events
     */
    protected function createFactory($resolver, $finder, $events): Factory
    {
        return new Factory($resolver, $finder, $events);
    }
}
