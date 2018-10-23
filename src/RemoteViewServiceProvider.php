<?php

namespace Antwerpes\RemoteView;

use Antwerpes\RemoteView\View\Factory;
use Antwerpes\RemoteView\View\FileViewFinder;
use GuzzleHttp\Client;
use Illuminate\View\ViewServiceProvider;

/**
 * Class RemoteViewServiceProvider
 *
 * @package Antwerpes\RemoteView
 */
class RemoteViewServiceProvider extends ViewServiceProvider
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

        $this->app->bind('view.finder', function ($app) {
            return new FileViewFinder(
                $app['files'],
                $app['config']['view.paths'],
                $app['config'],
                new Client($app['config']['remote-view.guzzle-config']),
                null
            );
        });
    }

    /**
     * Create a new Factory Instance.
     *
     * @param  \Illuminate\View\Engines\EngineResolver $resolver
     * @param  \Illuminate\View\ViewFinderInterface $finder
     * @param  \Illuminate\Contracts\Events\Dispatcher $events
     *
     * @return Factory
     */
    protected function createFactory($resolver, $finder, $events): Factory
    {
        return new Factory($resolver, $finder, $events);
    }
}
