<?php

namespace Antwerpes\RemoteBlade\View;

use Antwerpes\RemoteBlade\Exceptions\IgnoredUrlSuffixException;
use Antwerpes\RemoteBlade\Exceptions\RemoteHostNotConfiguredException;
use Antwerpes\RemoteBlade\Exceptions\RemoteTemplateNotFoundException;
use Illuminate\Filesystem\Filesystem;

/**
 * Class FileViewFinder
 *
 * @package Antwerpes\RemoteBlade\View
 */
class FileViewFinder extends \Illuminate\View\FileViewFinder
{
    /**
     * @var RemoteBladeFinder
     */
    private $remoteView;

    /**
     * Create a new file view loader instance.
     *
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param array $paths
     * @param RemoteBladeFinder $remoteView
     * @param array $extensions
     */
    public function __construct(
        Filesystem $files,
        array $paths,
        RemoteBladeFinder $remoteView,
        array $extensions = null
    ) {
        parent::__construct($files, $paths, $extensions);
        $this->remoteView = $remoteView;
    }

    /**
     * Get the fully qualified location of the view.
     *
     * @param string $name
     *
     * @return string
     * @throws IgnoredUrlSuffixException
     * @throws RemoteTemplateNotFoundException
     * @throws RemoteHostNotConfiguredException
     */
    public function find($name): string
    {
        if (isset($this->views[$name])) {
            return $this->views[$name];
        }

        if ($this->remoteView->hasRemoteInformation($name = trim($name)) === true) {
            return $this->views[$name] = $this->remoteView->findRemotePathView($name);
        }

        return parent::find($name);
    }
}
