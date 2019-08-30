<?php

use Schnoop\RemoteTemplate\View\Factory;
use Schnoop\RemoteTemplate\View\RemoteTemplateFinder;
use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * Class FileViewFinderTest
 */
class FileViewFinderTest extends TestCase
{

    /**
     * @var Factory
     */
    protected $instance;

    /**
     *
     */
    public function testFindPathWithoutRemoteIdentifier()
    {
        $mock = m::mock(RemoteTemplateFinder::class);
        $mock->shouldReceive('hasRemoteInformation')->with('hurz')->andReturnFalse();

        $this->instance = new \Schnoop\RemoteTemplate\View\FileViewFinder(
            m::mock(\Illuminate\Filesystem\Filesystem::class),
            [],
            $mock,
            []
        );

        $this->expectException(InvalidArgumentException::class);
        $this->instance->find('hurz');
    }

    /**
     *
     */
    public function testFindPathWithRemoteIdentifier()
    {
        $mock = m::mock(RemoteTemplateFinder::class);
        $mock->shouldReceive('hasRemoteInformation')->with('remote:hurz')->andReturnTrue();
        $mock->shouldReceive('findRemotePathView')->with('remote:hurz')->andReturn('blubb');
        $mock->shouldNotHaveReceived('findRemotePathView');

        $this->instance = new \Schnoop\RemoteTemplate\View\FileViewFinder(
            m::mock(\Illuminate\Filesystem\Filesystem::class),
            [],
            $mock,
            []
        );

        $this->assertEquals('blubb', $this->instance->find('remote:hurz'));
        $this->assertEquals('blubb', $this->instance->find('remote:hurz'));
    }
}
