<?php

use Antwerpes\RemoteView\Exceptions\IgnoredUrlSuffixException;
use Antwerpes\RemoteView\Exceptions\RemoteHostNotConfiguredException;
use Antwerpes\RemoteView\View\FileViewFinder;
use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * Class FileViewFinderTest
 */
class FileViewFinderTest extends TestCase
{

    /**
     * @var FileViewFinder
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
        $this->instance = new FileViewFinder(
            $this->getFilesystemMock(),
            [],
            $this->getConfigMock(),
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->expectException(InvalidArgumentException::class);
        $this->instance->find('dasLamm');
    }

    /**
     *
     */
    public function testNoRemoteHostConfigured()
    {
        $config = $this->getConfigMock();
        $config->shouldReceive('get')->with('remote-view.hosts')->andReturn([]);
        $this->instance = new FileViewFinder(
            $this->getFilesystemMock(),
            [],
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->expectException(RemoteHostNotConfiguredException::class);
        $this->expectExceptionMessage('No remote host configured for namespace # default. Please check your remote-view.php config file.');
        $this->instance->find('remote:dasLamm');
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
        $this->instance = new FileViewFinder(
            $this->getFilesystemMock(),
            [],
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->expectException(RemoteHostNotConfiguredException::class);
        $this->expectExceptionMessage('No remote host configured for namespace # specific. Please check your remote-view.php config file.');
        $this->instance->find('remote:specific::dasLamm');
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
        $this->instance = new FileViewFinder(
            $this->getFilesystemMock(),
            [],
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->expectException(IgnoredUrlSuffixException::class);
        $this->expectExceptionMessage('URL # dasLamm.svg has an ignored suffix.');
        $this->instance->find('remote:dasLamm.svg');
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
        $this->instance = new FileViewFinder(
            $this->getFilesystemMock(),
            [],
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->expectException(IgnoredUrlSuffixException::class);
        $this->expectExceptionMessage('URL # dasLamm.svg has an ignored suffix.');
        $this->instance->find('remote:dasLamm.svg');
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
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/default/daslamm.blade.php')->andReturn(true);

        $this->instance = new FileViewFinder(
            $fileSystemMock,
            [],
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->assertEquals('tests/default/daslamm.blade.php', $this->instance->find('remote:dasLamm'));
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
        $config->shouldReceive('get')->with('remote-view.view-folder')->andReturn('tests/');

        $fileSystemMock = $this->getFilesystemMock();
        $fileSystemMock->shouldReceive('exists')->with('tests/specific/daslamm.blade.php')->andReturn(true);

        $this->instance = new FileViewFinder(
            $fileSystemMock,
            [],
            $config,
            m::mock(\GuzzleHttp\Client::class)
        );

        $this->assertEquals('tests/specific/daslamm.blade.php', $this->instance->find('remote:specific::dasLamm'));
    }
}
