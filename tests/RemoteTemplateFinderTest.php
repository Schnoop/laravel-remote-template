<?php

namespace Schnoop\RemoteTemplate\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Response as IlluminateResponse;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Schnoop\RemoteTemplate\Exceptions\IgnoredUrlSuffixException;
use Schnoop\RemoteTemplate\Exceptions\RemoteHostNotConfiguredException;
use Schnoop\RemoteTemplate\Exceptions\RemoteTemplateNotFoundException;
use Schnoop\RemoteTemplate\Exceptions\UrlIsForbiddenException;
use Schnoop\RemoteTemplate\View\RemoteTemplateFinder;

class RemoteTemplateFinderTest extends TestCase
{
    /** @var RemoteTemplateFinder */
    protected $instance;

    public function test_no_remote_delimiter_found(): void
    {
        $this->instance = new RemoteTemplateFinder(
            $this->getFilesystemMock(),
            $this->getConfigMock(),
            m::mock(Client::class),
        );

        $this->assertFalse($this->instance->hasRemoteInformation('dasLamm'));
        $this->assertTrue($this->instance->hasRemoteInformation('remote:dasLamm'));
    }

    public function test_no_remote_host_configured(): void
    {
        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn([]);
        $this->instance = new RemoteTemplateFinder($this->getFilesystemMock(), $config, m::mock(Client::class));

        $this->expectException(RemoteHostNotConfiguredException::class);
        $this->expectExceptionMessage(
            'No remote host configured for namespace # default. Please check your remote-view.php config file.',
        );
        $this->instance->findRemotePathView('remote:dasLamm');
    }

    public function test_no_remote_host_configured_for_used_namespace(): void
    {
        $hosts = [
            'default' => [],
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $this->instance = new RemoteTemplateFinder($this->getFilesystemMock(), $config, m::mock(Client::class));

        $this->expectException(RemoteHostNotConfiguredException::class);
        $this->expectExceptionMessage(
            'No remote host configured for namespace # specific. Please check your remote-view.php config file.',
        );
        $this->instance->findRemotePathView('remote:specific::dasLamm');
    }

    public function test_file_suffix_is_on_global_ignore_list(): void
    {
        $hosts = [
            'default' => [],
        ];

        $ignoreFileList = ['svg'];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn($ignoreFileList);
        $this->instance = new RemoteTemplateFinder($this->getFilesystemMock(), $config, m::mock(Client::class));

        $this->expectException(IgnoredUrlSuffixException::class);
        $this->expectExceptionMessage('URL # dasLamm.svg has an ignored suffix.');
        $this->instance->findRemotePathView('remote:dasLamm.svg');
    }

    public function test_file_suffix_is_on_host_ignore_list(): void
    {
        $hosts = [
            'default' => [
                'ignore-url-suffix' => ['svg'],
            ],
        ];

        $ignoreFileList = ['jpg'];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn($ignoreFileList);
        $this->instance = new RemoteTemplateFinder($this->getFilesystemMock(), $config, m::mock(Client::class));

        $this->expectException(IgnoredUrlSuffixException::class);
        $this->expectExceptionMessage('URL # dasLamm.svg has an ignored suffix.');
        $this->instance->findRemotePathView('remote:dasLamm.svg');
    }

    public function test_caching_enabled_and_file_exists_for_default_view(): void
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

        $this->instance = new RemoteTemplateFinder($fileSystemMock, $config, m::mock(Client::class));

        $this->assertSame('tests/default/daslamm.blade.php', $this->instance->findRemotePathView('remote:dasLamm'));
    }

    public function test_caching_enabled_and_file_exists_for_namespaced_view(): void
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

        $this->instance = new RemoteTemplateFinder($fileSystemMock, $config, m::mock(Client::class));

        $this->assertSame(
            'tests/specific/daslamm.blade.php',
            $this->instance->findRemotePathView('remote:specific::dasLamm'),
        );
    }

    public function test_caching_is_disabled_and_file_exists_for_namespaced_view(): void
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

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', $responseMock);

        $clientMock = m::mock(Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/dasLamm', ['http_errors' => false])
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder($fileSystemMock, $config, $clientMock);

        $this->assertSame(
            'tests/specific/daslamm.blade.php',
            $this->instance->findRemotePathView('remote:specific::dasLamm'),
        );
    }

    public function test_caching_is_disabled_and_file_exists_for_namespaced_view_with_illuminate_response(): void
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

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getContent')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', $responseMock);

        $clientMock = m::mock(Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/dasLamm', ['http_errors' => false])
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder($fileSystemMock, $config, $clientMock);

        $this->assertSame(
            'tests/specific/daslamm.blade.php',
            $this->instance->findRemotePathView('remote:specific::dasLamm'),
        );
    }

    public function test_caching_is_disabled_and_file_exists_for_namespaced_view_with_guzzle_response(): void
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

        $responseMock = m::mock(Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', 'MyContent');

        $clientMock = m::mock(Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/dasLamm', ['http_errors' => false])
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder($fileSystemMock, $config, $clientMock);

        $this->assertSame(
            'tests/specific/daslamm.blade.php',
            $this->instance->findRemotePathView('remote:specific::dasLamm'),
        );
    }

    public function test_caching_is_disabled_and_file_not_exists_for_namespaced_view(): void
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

        $responseMock = m::mock(Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', 'MyContent');

        $clientMock = m::mock(Client::class);
        $clientMock->shouldReceive('get')->with('http://foo.bar/dasLamm', ['http_errors' => false])
            ->andThrow(Exception::class);

        $this->instance = new RemoteTemplateFinder($fileSystemMock, $config, $clientMock);

        $this->expectException(RemoteTemplateNotFoundException::class);
        $this->instance->findRemotePathView('remote:specific::dasLamm');
    }

    public function test_request_with_additional_options(): void
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

        $responseMock = m::mock(Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', 'MyContent');

        $clientMock = m::mock(Client::class);
        $clientMock->shouldReceive('get')->with(
            'http://foo.bar/dasLamm',
            ['auth_user' => 't', 'auth_password' => '', 'http_errors' => false],
        )
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder($fileSystemMock, $config, $clientMock);

        $this->assertSame(
            'tests/specific/daslamm.blade.php',
            $this->instance->findRemotePathView('remote:specific::dasLamm'),
        );
    }

    public function test_with_modify_template_url_callback(): void
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

        $responseMock = m::mock(Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/hurz.blade.php', 'MyContent');

        $clientMock = m::mock(Client::class);
        $clientMock->shouldReceive('get')->with(
            'http://foo.bar/hurz',
            ['auth_user' => 't', 'auth_password' => '', 'http_errors' => false],
        )
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder($fileSystemMock, $config, $clientMock);
        $this->instance->setModifyTemplateUrlCallback(fn ($url) => 'hurz');

        $this->assertSame(
            'tests/specific/hurz.blade.php',
            $this->instance->findRemotePathView('remote:specific::dasLamm'),
        );
    }

    public function test_with_response_handler(): void
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

        $responseMock = m::mock(Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(404);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', 'Blubb');

        $clientMock = m::mock(Client::class);
        $clientMock->shouldReceive('get')->with(
            'http://foo.bar/dasLamm',
            ['auth_user' => 't', 'auth_password' => '', 'http_errors' => false],
        )
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder($fileSystemMock, $config, $clientMock);
        $this->instance->pushResponseHandler(404, fn () => new IlluminateResponse('Blubb', 404));

        $this->assertSame(
            'tests/specific/daslamm.blade.php',
            $this->instance->findRemotePathView('remote:specific::dasLamm'),
        );
    }

    public function test_with_response_handlers_array(): void
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

        $responseMock = m::mock(Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(405);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/daslamm.blade.php', 'Blubb');

        $clientMock = m::mock(Client::class);
        $clientMock->shouldReceive('get')->with(
            'http://foo.bar/dasLamm',
            ['auth_user' => 't', 'auth_password' => '', 'http_errors' => false],
        )
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder($fileSystemMock, $config, $clientMock);
        $this->instance->pushResponseHandler([405], fn () => new IlluminateResponse('Blubb', 405));

        $this->assertSame(
            'tests/specific/daslamm.blade.php',
            $this->instance->findRemotePathView('remote:specific::dasLamm'),
        );
    }

    public function test_url_is_on_default_host_forbidden_list(): void
    {
        $hosts = [
            'default' => [
                'ignore-urls' => ['typo3'],
            ],
        ];

        $ignoreUrlList = [
            // '/typo3/index.php',
            // 'typo3',
        ];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn($ignoreUrlList);
        $this->instance = new RemoteTemplateFinder($this->getFilesystemMock(), $config, m::mock(Client::class));

        $this->expectException(UrlIsForbiddenException::class);
        $this->expectExceptionMessage('URL # typo3/ is forbidden.');
        $this->instance->findRemotePathView('remote:typo3/');
    }

    public function test_url_is_on_general_host_forbidden_list(): void
    {
        $hosts = [
            'default' => [
                'ignore-urls' => [],
            ],
        ];

        $ignoreUrlList = ['typo3'];

        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn($hosts);
        $config->shouldReceive('get')->with('remote-view.ignore-url-suffix')->andReturn([]);
        $config->shouldReceive('get')->with('remote-view.ignore-urls')->andReturn($ignoreUrlList);
        $this->instance = new RemoteTemplateFinder($this->getFilesystemMock(), $config, m::mock(Client::class));

        $this->expectException(UrlIsForbiddenException::class);
        $this->expectExceptionMessage('URL # typo3 is forbidden.');
        $this->instance->findRemotePathView('remote:typo3');
    }

    public function test_with_different_view_file(): void
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

        $responseMock = m::mock(Response::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody->getContents')->andReturn('MyContent');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/dong.blade.php')->andReturn(true);
        $fileSystemMock->shouldReceive('put')->with('tests/specific/dong.blade.php', 'MyContent');

        $clientMock = m::mock(Client::class);
        $clientMock->shouldReceive('get')->with(
            'http://foo.bar/dasLamm',
            ['auth_user' => 't', 'auth_password' => '', 'http_errors' => false],
        )
            ->andReturn($responseMock);

        $this->instance = new RemoteTemplateFinder($fileSystemMock, $config, $clientMock);
        $this->instance->setViewFilenameCallback(fn ($url) => 'dong.blade.php');

        $this->assertSame(
            'tests/specific/dong.blade.php',
            $this->instance->findRemotePathView('remote:specific::dasLamm'),
        );
    }

    /**
     * @return m\MockInterface
     */
    private function getFilesystemMock()
    {
        return m::mock(Filesystem::class);
    }

    /**
     * @return m\MockInterface
     */
    private function getConfigMock()
    {
        $config = m::mock(Repository::class);
        $config->shouldReceive('get')->with('remote-view.remote-delimiter')->andReturn('remote:');

        return $config;
    }
}
