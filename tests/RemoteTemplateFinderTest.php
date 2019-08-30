<?php

use Antwerpes\RemoteTemplate\Exceptions\IgnoredUrlSuffixException;
use Antwerpes\RemoteTemplate\Exceptions\RemoteHostNotConfiguredException;
use Antwerpes\RemoteTemplate\Exceptions\UrlIsForbiddenException;
use Antwerpes\RemoteTemplate\View\RemoteTemplateFinder;
use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * Class RemoteTemplateFinderTest
 */
class RemoteTemplateFinderTest extends TestCase
{

    /**
     * @var RemoteTemplateFinder
     */
    protected $instance;

    /**
     * @return m\MockInterface
     */
    private function getFilesystemMock()
    {
        return m::mock(\Illuminate\Filesystem\Filesystem::class);
    }

    /**
     * @return m\MockInterface
     */
    private function getConfigMock()
    {
        $config = m::mock(\Illuminate\Contracts\Config\Repository::class);
        $config->shouldReceive('get')->with('remote-view.remote-delimiter')->andReturn('remote:');
        return $config;
    }

    /**
     *
     */
    public function testNoRemoteDelimiterFound()
    {
        $this->instance = new RemoteTemplateFinder(
            $this->getFilesystemMock(),
            $this->getConfigMock(),
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->assertFalse($this->instance->hasRemoteInformation('dasLamm'));
        $this->assertTrue($this->instance->hasRemoteInformation('remote:dasLamm'));
    }

    /**
     *
     */
    public function testNoRemoteHostConfigured()
    {
        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn([]);
        $this->instance = new RemoteTemplateFinder(
            $this->getFilesystemMock(),
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->expectException(RemoteHostNotConfiguredException::class);
        $this->expectExceptionMessage('No remote host configured for namespace # default. Please check your remote-view.php config file.');
        $this->instance->findRemotePathView('remote:dasLamm');
    }

    /**
     *
     */
    public function testNoRemoteHostConfiguredForUsedNamespace()
    {
        $hosts = [
            'default' => [
            ],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $this->instance = new RemoteTemplateFinder(
            $this->getFilesystemMock(),
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->expectException(RemoteHostNotConfiguredException::class);
        $this->expectExceptionMessage('No remote host configured for namespace # specific. Please check your remote-view.php config file.');
        $this->instance->findRemotePathView('remote:specific::dasLamm');
    }

    /**
     *
     */
    public function testFileSuffixIsOnGlobalIgnoreList()
    {
        $hosts = [
            'default' => [
            ],
        ];

        $ignoreFileList = [
            'svg'
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn($ignoreFileList);
        $this->instance = new RemoteTemplateFinder(
            $this->getFilesystemMock(),
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->expectException(IgnoredUrlSuffixException::class);
        $this->expectExceptionMessage('URL # dasLamm.svg has an ignored suffix.');
        $this->instance->findRemotePathView('remote:dasLamm.svg');
    }

    /**
     *
     */
    public function testFileSuffixIsOnHostIgnoreList()
    {
        $hosts = [
            'default' => [
                'ignore-url-suffix' => [
                    'svg'
                ]
            ],
        ];

        $ignoreFileList = [
            'jpg'
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn($ignoreFileList);
        $this->instance = new RemoteTemplateFinder(
            $this->getFilesystemMock(),
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->expectException(IgnoredUrlSuffixException::class);
        $this->expectExceptionMessage('URL # dasLamm.svg has an ignored suffix.');
        $this->instance->findRemotePathView('remote:dasLamm.svg');
    }

    /**
     *
     */
    public function testCachingEnabledAndFileExistsForDefaultView()
    {
        $hosts = [
            'default' => [
                'cache' => true,
            ],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/default/daslamm.blade.php')->andReturn(true);

        $this->instance = new RemoteTemplateFinder(
            $fileSystemMock,
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->assertEquals('tests/default/daslamm.blade.php', $this->instance->findRemotePathView('remote:dasLamm'));
    }

    /**
     *
     */
    public function testCachingEnabledAndFileExistsForNamespacedView()
    {
        $hosts = [
            'specific' => [
                'cache' => true,
            ],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);

        $this->instance = new RemoteTemplateFinder(
            $fileSystemMock,
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->assertEquals('tests/specific/daslamm.blade.php', $this->instance->findRemotePathView('remote:specific::dasLamm'));
    }

    /**
     *
     */
    public function testCachingIsDisabledAndFileExistsForNamespacedView()
    {
        $hosts = [
            'specific' => [
                'cache' => false,
                'host' => 'http://foo.bar',
            ],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $responseMock = m::mock(\Psr\Http\Message\ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', $responseMock);


        $clientMock = m::mock(\GuzzleHttp\Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/dasLamm', ['http_errors' => false])
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder(
            $fileSystemMock,
            $config,
            $clientMock
        );

        $this->assertEquals('tests/specific/daslamm.blade.php', $this->instance->findRemotePathView('remote:specific::dasLamm'));
    }

    /**
     *
     */
    public function testCachingIsDisabledAndFileExistsForNamespacedViewWithIlluminateResponse()
    {
        $hosts = [
            'specific' => [
                'cache' => false,
                'host' => 'http://foo.bar',
            ],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $responseMock = m::mock(\Illuminate\Http\Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getContent')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', 'MyContent');


        $clientMock = m::mock(\GuzzleHttp\Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/dasLamm', ['http_errors' => false])
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder(
            $fileSystemMock,
            $config,
            $clientMock
        );

        $this->assertEquals('tests/specific/daslamm.blade.php', $this->instance->findRemotePathView('remote:specific::dasLamm'));
    }

    /**
     *
     */
    public function testCachingIsDisabledAndFileExistsForNamespacedViewWithGuzzleResponse()
    {
        $hosts = [
            'specific' => [
                'cache' => false,
                'host' => 'http://foo.bar',
            ],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $responseMock = m::mock(\GuzzleHttp\Psr7\Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', 'MyContent');


        $clientMock = m::mock(\GuzzleHttp\Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/dasLamm', ['http_errors' => false])
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder(
            $fileSystemMock,
            $config,
            $clientMock
        );

        $this->assertEquals('tests/specific/daslamm.blade.php', $this->instance->findRemotePathView('remote:specific::dasLamm'));
    }

    /**
     *
     */
    public function testCachingIsDisabledAndFileNotExistsForNamespacedView()
    {
        $hosts = [
            'specific' => [
                'cache' => false,
                'host' => 'http://foo.bar',
            ],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $responseMock = m::mock(\GuzzleHttp\Psr7\Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', 'MyContent');


        $clientMock = m::mock(\GuzzleHttp\Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/dasLamm', ['http_errors' => false])
            ->andThrow(Exception::class);

        $this->instance = new RemoteTemplateFinder(
            $fileSystemMock,
            $config,
            $clientMock
        );

        $this->expectException(\Antwerpes\RemoteTemplate\Exceptions\RemoteTemplateNotFoundException::class);
        $this->instance->findRemotePathView('remote:specific::dasLamm');
    }

    /**
     *
     */
    public function testRequestWithAdditionalOptions()
    {
        $hosts = [
            'specific' => [
                'cache' => false,
                'host' => 'http://foo.bar',
                'request_options' => [
                    'auth_user' => 't',
                    'auth_password' => '',
                ],
            ],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $responseMock = m::mock(\GuzzleHttp\Psr7\Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', 'MyContent');


        $clientMock = m::mock(\GuzzleHttp\Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/dasLamm', ['auth_user' => 't', 'auth_password' => '', 'http_errors' => false])
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder(
            $fileSystemMock,
            $config,
            $clientMock
        );

        $this->assertEquals('tests/specific/daslamm.blade.php', $this->instance->findRemotePathView('remote:specific::dasLamm'));
    }


    /**
     *
     */
    public function testWithModifyTemplateUrlCallback()
    {
        $hosts = [
            'specific' => [
                'cache' => false,
                'host' => 'http://foo.bar',
                'request_options' => [
                    'auth_user' => 't',
                    'auth_password' => '',
                ],
            ],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $responseMock = m::mock(\GuzzleHttp\Psr7\Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/hurz.blade.php', 'MyContent');


        $clientMock = m::mock(\GuzzleHttp\Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/hurz', ['auth_user' => 't', 'auth_password' => '', 'http_errors' => false])
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder(
            $fileSystemMock,
            $config,
            $clientMock
        );
        $this->instance->setModifyTemplateUrlCallback(function ($url) {
            return 'hurz';
        });

        $this->assertEquals('tests/specific/hurz.blade.php', $this->instance->findRemotePathView('remote:specific::dasLamm'));
    }


    /**
     *
     */
    public function testWithResponseHandler()
    {
        $hosts = [
            'specific' => [
                'cache' => false,
                'host' => 'http://foo.bar',
                'request_options' => [
                    'auth_user' => 't',
                    'auth_password' => '',
                ],
            ],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $responseMock = m::mock(\GuzzleHttp\Psr7\Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(404);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', 'Blubb');


        $clientMock = m::mock(\GuzzleHttp\Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/dasLamm', ['auth_user' => 't', 'auth_password' => '', 'http_errors' => false])
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder(
            $fileSystemMock,
            $config,
            $clientMock
        );
        $this->instance->pushResponseHandler(404, function () {
            return 'Blubb';
        });

        $this->assertEquals('tests/specific/daslamm.blade.php', $this->instance->findRemotePathView('remote:specific::dasLamm'));
    }


    /**
     *
     */
    public function testWithResponseHandlersArray()
    {
        $hosts = [
            'specific' => [
                'cache' => false,
                'host' => 'http://foo.bar',
                'request_options' => [
                    'auth_user' => 't',
                    'auth_password' => '',
                ],
            ],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $responseMock = m::mock(\GuzzleHttp\Psr7\Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(405);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', 'Blubb');


        $clientMock = m::mock(\GuzzleHttp\Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/dasLamm', ['auth_user' => 't', 'auth_password' => '', 'http_errors' => false])
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder(
            $fileSystemMock,
            $config,
            $clientMock
        );
        $this->instance->pushResponseHandler([405], function () {
            return 'Blubb';
        });

        $this->assertEquals('tests/specific/daslamm.blade.php', $this->instance->findRemotePathView('remote:specific::dasLamm'));
    }


    /**
     *
     */
    public function testUrlIsOnDefaultHostForbiddenList()
    {
        $hosts = [
            'default' => [
                'ignore-urls' => [
                    'typo3'
                ]
            ],
        ];

        $ignoreUrlList = [
            //'/typo3/index.php',
            //'typo3',
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn($ignoreUrlList);
        $this->instance = new RemoteTemplateFinder(
            $this->getFilesystemMock(),
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->expectException(UrlIsForbiddenException::class);
        $this->expectExceptionMessage('URL # typo3/ is forbidden.');
        $this->instance->findRemotePathView('remote:typo3/');
    }

    /**
     *
     */
    public function testUrlIsOnGeneralHostForbiddenList()
    {
        $hosts = [
            'default' => [
                'ignore-urls' => [
                ]
            ],
        ];

        $ignoreUrlList = [
            'typo3',
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn($ignoreUrlList);
        $this->instance = new RemoteTemplateFinder(
            $this->getFilesystemMock(),
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->expectException(UrlIsForbiddenException::class);
        $this->expectExceptionMessage('URL # typo3 is forbidden.');
        $this->instance->findRemotePathView('remote:typo3');
    }
}
