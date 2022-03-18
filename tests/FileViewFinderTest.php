<?php

namespace Schnoop\RemoteTemplate\Tests;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Schnoop\RemoteTemplate\View\Factory;
use Schnoop\RemoteTemplate\View\FileViewFinder;
use Schnoop\RemoteTemplate\View\RemoteTemplateFinder;

class FileViewFinderTest extends TestCase
{
    /** @var Factory */
    protected $instance;

    public function test_find_path_without_remote_identifier(): void
    {
        $mock = m::mock(RemoteTemplateFinder::class);
        $mock->shouldReceive('hasRemoteInformation')->with('hurz')->andReturnFalse();

        $this->instance = new FileViewFinder(m::mock(Filesystem::class), [], $mock, []);

        $this->expectException(InvalidArgumentException::class);
        $this->instance->find('hurz');
    }

    public function test_find_path_with_remote_identifier(): void
    {
        $mock = m::mock(RemoteTemplateFinder::class);
        $mock->shouldReceive('hasRemoteInformation')->with('remote:hurz')->andReturnTrue();
        $mock->shouldReceive('findRemotePathView')->with('remote:hurz')->andReturn('blubb');
        $mock->shouldNotHaveReceived('findRemotePathView');

        $this->instance = new FileViewFinder(m::mock(Filesystem::class), [], $mock, []);

        $this->assertSame('blubb', $this->instance->find('remote:hurz'));
        $this->assertSame('blubb', $this->instance->find('remote:hurz'));
    }
}
