<?php

use Illuminate\Routing\Router;

/**
 * Class FeatureTest.
 */
class FeatureTest extends \Orchestra\Testbench\TestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return ['Schnoop\RemoteTemplate\RemoteTemplateServiceProvider'];
    }

    /**
     * Define environment setup.
     *
     * @param  Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('view.paths', [realpath(('tests/views'))]);
        $app['config']->set('remote-view.hosts.default.host', 'http://www.google.com');
        $router = $app['router'];
        $this->addWebRoutes($router);
    }

    /**
     * @param  Router  $router
     */
    protected function addWebRoutes(Router $router)
    {
        $router->get('web/200', [
            'as' => 'web.200',
            'uses' => function () {
                return view('200');
            },
        ]);
        $router->get('web/404', [
            'as' => 'web.404',
            'uses' => function () {
                return view('404');
            },
        ]);
    }

    public function testResponseOk()
    {
        $crawler = $this->call('GET', 'web/200');
        $crawler->assertStatus(200);
    }

    public function testResponseIs500IfDomainNotFound()
    {
        $this->app['config']->set('remote-view.hosts.default.host', 'http://www.googlasdade.com');
        $crawler = $this->call('GET', 'web/200');
        $crawler->assertStatus(500);
    }

    public function testResponseHandlerIfResponseIs404()
    {
        $this->app['config']->set('remote-view.hosts.default.host', 'http://www.google.com');
        $this->app->make('remoteview.finder')->pushResponseHandler(404,
            function (\GuzzleHttp\Psr7\Response $result, array $config, \Schnoop\RemoteTemplate\View\RemoteTemplateFinder $service) {
                return 'This content has been modified through a response handler.';
            });
        $crawler = $this->call('GET', 'web/404');
        $crawler->assertSee('This content has been modified through a response handler.');
    }
}
