<?php

namespace Antwerpes\RemoteTemplate;

use Antwerpes\RemoteTemplate\View\Factory;
use Antwerpes\RemoteTemplate\View\FileViewFinder;
use Antwerpes\RemoteTemplate\View\RemoteTemplateFinder;
use GuzzleHttp\Client;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\ViewFinderInterface;
use Illuminate\View\ViewServiceProvider;

/**
 * Class RemoteTemplateServiceProvider
 *
 * @package Antwerpes\RemoteTemplate
 */
class RemoteTemplateServiceProvider extends ViewServiceProvider
{

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/remote-view.php' => config_path('remote-view.php'),
            ], 'config');
        }
    }

    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    public function registerViewFinder(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/remote-view.php', 'remote-view');

        $this->app->singleton('remoteview.finder', function ($app) {
            return new RemoteTemplateFinder(
                $app['files'],
                $app['config'],
                new Client($app['config']['remote-view.guzzle-config'])
            );
        });

        $this->app->singleton('view.finder', function ($app) {
            return new FileViewFinder(
                $app['files'],
                $app['config']['view.paths'],
                $app['remoteview.finder'],
                null
            );
        });
    }

    /**
     * Create a new Factory Instance.
     *
     * @param  EngineResolver $resolver
     * @param  ViewFinderInterface $finder
     * @param  Dispatcher $events
     *
     * @return Factory
     */
    protected function createFactory($resolver, $finder, $events): Factory
    {
        return new Factory($resolver, $finder, $events);
    }
}
