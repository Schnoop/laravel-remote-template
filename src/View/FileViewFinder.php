<?php

namespace Antwerpes\RemoteView\View;

use Antwerpes\RemoteView\Exceptions\IgnoredUrlSuffixException;
use Antwerpes\RemoteView\Exceptions\RemoteTemplateNotFoundException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

/**
 * Class FileViewFinder.
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
     */
    public function find($name)
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
    protected function hasRemoteInformation($name)
    {
        return strpos($name, $this->remotePathDelimiter) === 0
            && strpos($name, $this->remotePathDelimiter) !== false;
    }

    /**
     * Fetch template from remote resource, store locally and return path to local file.
     *
     * @param string $name Remote URL to fetch template from
     *
     * @return string
     * @throws IgnoredUrlSuffixException
     * @throws RemoteTemplateNotFoundException
     */
    protected function findRemotePathView($name)
    {
        $name = trim(str_replace($this->remotePathDelimiter, '', $name));

        $namespace = 'default';
        if ($this->hasNamespace($name) === true) {
            list($namespace, $name) = $this->parseRemoteNamespaceSegments($name);
        }

        $remoteHost = $this->getRemoteHost($namespace);

        // Check if URL suffix is ignored
        if ($this->urlHasIgnoredSuffix($name, $remoteHost) === true) {
            throw new IgnoredUrlSuffixException();
        }

        $filename = $name;
        $path = base_path('resources/views/remote/' . $namespace . '/' . str_slug($filename) . '.blade.php');
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
     */
    public function getRemoteHost($namespace)
    {
        $config = $this->config->get('remote-view.hosts');
        return $config[$namespace];
    }

    /**
     * Check for valid namespace.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasNamespace($name)
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
     */
    protected function parseRemoteNamespaceSegments($name)
    {
        $segments = explode(static::HINT_PATH_DELIMITER, $name);

        if (count($segments) !== 2) {
            throw new InvalidArgumentException("View [{$name}] has an invalid name.");
        }
        $config = $this->config->get('remote-view.hosts');
        if (!isset($config[$segments[0]])) {
            throw new InvalidArgumentException("No hint path defined for [{$segments[0]}].");
        }

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
    private function urlHasIgnoredSuffix($url, $remoteHost)
    {
        $parsedUrl = parse_url($url, PHP_URL_PATH);
        $pathInfo = pathinfo($parsedUrl, PATHINFO_EXTENSION);
        return in_array($pathInfo, $this->config->get('remote-view.ignore-url-suffix'));
    }

    /**
     * Returns remote url for given $identifier
     *
     * @param string $identifier
     * @param array $remoteHost
     *
     * @return string
     */
    private function getTemplateUrlForIdentifier($identifier, $remoteHost)
    {
        $route = null;
        if (isset($remoteHost['mapping'][$identifier]) === true) {
            $route = $remoteHost['mapping'][$identifier];
        }
        if ($route === null) {
            $route = $identifier;
        }
        if (strpos($route, '/') > 0) {
            return '/' . $route;
        }
        return $route;
    }

    /**
     * Returns string for request.
     *
     * @param string $glue
     *
     * @return string
     */
    private function getUserAppendix($glue = '?')
    {

    }

    /**
     * Fetch content from $url
     *
     * @param string $url
     * @param array $remoteHost
     *
     * @return \GuzzleHttp\Psr7\Response
     * @throws RemoteTemplateNotFoundException
     */
    private function fetchContentFromRemoteHost($url, $remoteHost)
    {
        $options = [];
        if (isset($remoteHost['request_options']) === true) {
            $options = $remoteHost['request_options'];
        }
        try {
            $res = $this->client->get($url, $options);

            // Response is a redirect status code.
            if ((int)$res->getStatusCode() > 300 && (int)$res->getStatusCode() < 308) {
                $location = parse_url($res->getHeader('Location'){0});
                $remoteContent = parse_url($this->remoteContentHost);

                // Looks like we have a redirect away. So then.... have a pleasant journey.
                if ($location['host'] !== $remoteContent['host']) {
                    $res = view('partials.redirect')->with('location', $res->getHeader('Location'){0});
                } else {
                    $res = $this->fetchContentFromRemoteHost($res->getHeader('Location'){0});
                }

            }
        } catch (Exception $e) {
            throw new RemoteTemplateNotFoundException($url, 404);
        }
        return $res;
    }
}
