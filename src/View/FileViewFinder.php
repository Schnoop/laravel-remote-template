<?php

namespace Antwerpes\RemoteView\View;

use Antwerpes\RemoteView\Exceptions\IgnoredUrlSuffixException;
use Antwerpes\RemoteView\Exceptions\RemoteHostNotConfiguredException;
use Antwerpes\RemoteView\Exceptions\RemoteTemplateNotFoundException;
use Illuminate\Filesystem\Filesystem;

/**
 * Class FileViewFinder
 *
 * @package Antwerpes\RemoteView\View
 */
class FileViewFinder extends \Illuminate\View\FileViewFinder
{
    /**
     * @var RemoteViewFinder
     */
    private $remoteView;

    /**
     * Create a new file view loader instance.
     *
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param array $paths
     * @param RemoteViewFinder $remoteView
     * @param array $extensions
     */
    public function __construct(
        Filesystem $files,
        array $paths,
        RemoteViewFinder $remoteView,
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

        if ($this->remoteView->hasRemoteInformation($name = trim($name))) {
            return $this->views[$name] = $this->remoteView->findRemotePathView($name);
        }

        return parent::find($name);
    }
}
