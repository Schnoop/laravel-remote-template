<?php declare(strict_types=1);

namespace Schnoop\RemoteTemplate\Tests;

use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Application;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase;
use Schnoop\RemoteTemplate\View\RemoteTemplateFinder;

class FeatureTest extends TestCase
{
    public function test_response_ok(): void
    {
        $crawler = $this->call('GET', 'web/200');
        $crawler->assertStatus(200);
    }

    public function test_response_is500_if_domain_not_found(): void
    {
        $this->app['config']->set('remote-view.hosts.default.host', 'http://www.googlasdade.com');
        $crawler = $this->call('GET', 'web/200');
        $crawler->assertStatus(500);
    }

    public function test_response_handler_if_response_is404(): void
    {
        $this->app['config']->set('remote-view.hosts.default.host', 'http://www.google.com');
        $this->app->make('remoteview.finder')->pushResponseHandler(
            404,
            fn (Response $result, array $config, RemoteTemplateFinder $service) => new IlluminateResponse(
                'This content has been modified through a response handler.',
                405,
            ),
        );
        $crawler = $this->call('GET', 'web/404');
        $crawler->assertSee('This content has been modified through a response handler.');
    }

    /**
     * @param Application $app
     */
    protected function getPackageProviders($app): array
    {
        return ['Schnoop\RemoteTemplate\RemoteTemplateServiceProvider'];
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('view.paths', [realpath('tests/views')]);
        $app['config']->set('remote-view.hosts.default.host', 'http://www.google.com');
        $router = $app['router'];
        $this->addWebRoutes($router);
    }

    protected function addWebRoutes(Router $router): void
    {
        $router->get('web/200', [
            'as' => 'web.200',
            'uses' => fn () => view('200'),
        ]);
        $router->get('web/404', [
            'as' => 'web.404',
            'uses' => fn () => view('404'),
        ]);
    }
}
