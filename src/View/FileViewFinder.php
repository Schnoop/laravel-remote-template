<?php

namespace Schnoop\RemoteTemplate\View;

use Illuminate\Filesystem\Filesystem;
use Schnoop\RemoteTemplate\Exceptions\IgnoredUrlSuffixException;
use Schnoop\RemoteTemplate\Exceptions\RemoteHostNotConfiguredException;
use Schnoop\RemoteTemplate\Exceptions\RemoteTemplateNotFoundException;
use Schnoop\RemoteTemplate\Exceptions\UrlIsForbiddenException;

/**
 * Class FileViewFinder.
 */
class FileViewFinder extends \Illuminate\View\FileViewFinder
{
    /**
     * @var RemoteTemplateFinder
     */
    private $remoteView;

    /**
     * Create a new file view loader instance.
     *
     * @param Filesystem $files
     * @param array $paths
     * @param RemoteTemplateFinder $remoteView
     * @param array $extensions
     */
    public function __construct(
        Filesystem $files,
        array $paths,
        RemoteTemplateFinder $remoteView,
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
     * @throws UrlIsForbiddenException
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
