<?php

namespace Schnoop\RemoteTemplate\View;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\FileViewFinder as LaravelFileViewFinder;
use Schnoop\RemoteTemplate\Exceptions\IgnoredUrlSuffixException;
use Schnoop\RemoteTemplate\Exceptions\RemoteHostNotConfiguredException;
use Schnoop\RemoteTemplate\Exceptions\RemoteTemplateNotFoundException;
use Schnoop\RemoteTemplate\Exceptions\UrlIsForbiddenException;

class FileViewFinder extends LaravelFileViewFinder
{
    /**
     * Create a new file view loader instance.
     */
    public function __construct(
        Filesystem $files,
        array $paths,
        private RemoteTemplateFinder $remoteView,
        ?array $extensions = null,
    ) {
        parent::__construct($files, $paths, $extensions);
    }

    /**
     * Get the fully qualified location of the view.
     *
     * @param string $name
     *
     * @throws IgnoredUrlSuffixException
     * @throws RemoteTemplateNotFoundException
     * @throws RemoteHostNotConfiguredException
     * @throws UrlIsForbiddenException
     * @throws GuzzleException
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
