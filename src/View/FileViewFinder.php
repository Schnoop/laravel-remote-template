<?php

namespace Antwerpes\RemoteView\View;

use Antwerpes\RemoteView\Exceptions\IgnoredUrlSuffixException;
use Antwerpes\RemoteView\Exceptions\RemoteHostNotConfiguredException;
use Antwerpes\RemoteView\Exceptions\RemoteTemplateNotFoundException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class FileViewFinder
 *
 * @package Antwerpes\RemoteView\View
 */
class FileViewFinder extends \Illuminate\View\FileViewFinder
{

    /**
     * @var string
     */
    protected $remotePathDelimiter;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Repository
     */
    private $config;

    /**
     * Create a new file view loader instance.
     *
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param array $paths
     * @param Repository $config
     * @param Client $client
     * @param array $extensions
     */
    public function __construct(
        Filesystem $files,
        array $paths,
        Repository $config,
        Client $client,
        array $extensions = null
    ) {
        parent::__construct($files, $paths, $extensions);
        $this->client = $client;
        $this->files = $files;
        $this->config = $config;
        $this->remotePathDelimiter = $this->config->get('remote-view.remote-delimiter');
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

        if ($this->hasRemoteInformation($name = trim($name))) {
            return $this->views[$name] = $this->findRemotePathView($name);
        }

        return parent::find($name);
    }

    /**
     * Returns true if template is a remote resource.
     *
     * @param string $name Name of template
     *
     * @return bool
     */
    protected function hasRemoteInformation($name): bool
    {
        return Str::startsWith($name, $this->remotePathDelimiter);
    }

    /**
     * Fetch template from remote resource, store locally and return path to local file.
     *
     * @param string $name Remote URL to fetch template from
     *
     * @return string
     * @throws IgnoredUrlSuffixException
     * @throws RemoteTemplateNotFoundException
     * @throws RemoteHostNotConfiguredException
     */
    protected function findRemotePathView($name): string
    {
        $name = trim(Str::replaceFirst($this->remotePathDelimiter, '', $name));

        $namespace = 'default';
        if ($this->hasNamespace($name) === true) {
            [$namespace, $name] = $this->parseRemoteNamespaceSegments($name);
        }

        $remoteHost = $this->getRemoteHost($namespace);

        // Check if URL suffix is ignored
        if ($this->urlHasIgnoredSuffix($name, $remoteHost) === true) {
            throw new IgnoredUrlSuffixException('URL # ' . $name . ' has an ignored suffix.');
        }

        $path = base_path('resources/views/remote-view-cache/' . $namespace . '/');
        if (mkdir($path, 0777, true) === false && is_dir($path) === false) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        $path = $path . Str::slug($name) . '.blade.php';
        if ($remoteHost['cache'] === true && $this->files->exists($path) === true) {
            return $path;
        }

        $name = $this->getTemplateUrlForIdentifier($name, $remoteHost);
        $name = $remoteHost['host'] . $name;

        $content = $this->fetchContentFromRemoteHost($name, $remoteHost);
        if ($content instanceof Response === true) {
            $content = $content->getBody()->getContents();
        }

        $this->files->put($path, $content);

        return $path;
    }

    /**
     * Return array with remote host conifg.
     *
     * @param string $namespace
     *
     * @return array
     * @throws RemoteHostNotConfiguredException
     */
    private function getRemoteHost($namespace): array
    {
        $config = $this->config->get('remote-view.hosts');
        if (isset($config[$namespace]) === false) {
            throw new RemoteHostNotConfiguredException('No remote host configured for namespace # '
                . $namespace . '. Please check your remote-view.php config file.');
        }
        return $config[$namespace];
    }

    /**
     * Check for valid namespace.
     *
     * @param string $name
     *
     * @return bool
     */
    private function hasNamespace($name): bool
    {
        try {
            $this->parseRemoteNamespaceSegments($name);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Get the segments of a template with a named path.
     *
     * @param  string $name
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     * @throws RemoteHostNotConfiguredException
     */
    protected function parseRemoteNamespaceSegments($name): array
    {
        $segments = explode(static::HINT_PATH_DELIMITER, $name);
        if (count($segments) !== 2) {
            throw new InvalidArgumentException("View [{$name}] has an invalid name.");
        }
        $this->getRemoteHost($segments[0]);
        return $segments;
    }

    /**
     * Returns true if given url is static.
     *
     * @param string $url
     * @param array $remoteHost
     *
     * @return bool
     */
    private function urlHasIgnoredSuffix($url, $remoteHost): bool
    {
        $parsedUrl = parse_url($url, PHP_URL_PATH);
        $pathInfo = pathinfo($parsedUrl, PATHINFO_EXTENSION);

        $ignoreUrlSuffix = $this->config->get('remote-view.ignore-url-suffix');
        if (isset($remoteHost['ignore-url-suffix']) === true) {
            $ignoreUrlSuffix = $remoteHost['ignore-url-suffix'];
        }
        return Arr::has($pathInfo, $ignoreUrlSuffix);
    }

    /**
     * Returns remote url for given $identifier
     *
     * @param string $identifier
     * @param array $remoteHost
     *
     * @return string
     */
    private function getTemplateUrlForIdentifier($identifier, $remoteHost): string
    {
        $route = $identifier;
        if (isset($remoteHost['mapping'][$identifier]) === true) {
            $route = $remoteHost['mapping'][$identifier];
        }
        if (strpos($route, '/') > 0) {
            return '/' . $route;
        }
        return $route;
    }

    /**
     * Fetch content from $url
     *
     * @param string $url
     * @param array $remoteHost
     *
     * @throws RemoteTemplateNotFoundException
     */
    private function fetchContentFromRemoteHost($url, $remoteHost): Response
    {
        $options = [];
        if (isset($remoteHost['request_options']) === true) {
            $options = $remoteHost['request_options'];
        }
        try {
            return $this->client->get($url, $options);
        } catch (Exception $e) {
            throw new RemoteTemplateNotFoundException($url, 404);
        }
    }
}
