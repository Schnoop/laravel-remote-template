<?php

namespace Antwerpes\RemoteView\View;

use Antwerpes\RemoteView\Exceptions\IgnoredUrlSuffixException;
use Antwerpes\RemoteView\Exceptions\RemoteTemplateNotFoundException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;

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

        // Check if URL suffix is ignored
        if ($this->urlHasIgnoredSuffix($name) === true) {
            throw new IgnoredUrlSuffixException();
        }

        $filename = $name;
        $path = base_path('resources/views/remote/' . str_slug($filename) . '.blade.php');
        if ($this->config->get('remote-view.cache') === true
            && $this->files->exists($path) === true
        ) {
            return $path;
        }

        $name = $this->getTemplateUrlForIdentifierAndContext($name);

        $name = $this->remoteContentHost . $name;
        $content = $this->fetchContentFromRemoteHost($name);

        if ($content instanceof Response === true) {
            $content = $content->getBody()->getContents();
        }

        $this->files->put($path, $content);

        return $path;
    }

    /**
     * Returns true if given url is static.
     *
     * @param string $url
     *
     * @return bool
     */
    private function urlHasIgnoredSuffix($url)
    {
        $parsedUrl = parse_url($url, PHP_URL_PATH);
        $pathInfo = pathinfo($parsedUrl, PATHINFO_EXTENSION);
        return in_array($pathInfo, $this->config->get('remote-view.ignore-url-suffix'));
    }

    /**
     * Returns remote url for given $identifier
     *
     * @param string $identifier
     *
     * @return string
     */
    private function getTemplateUrlForIdentifierAndContext($identifier)
    {
        $route = $this->config->get('aral-remote-view.mapping.' . $context . '.' . $identifier);
        if ($route === null) {
            // td: TODO: This is an evil fallback. We should remove this one!
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
     *
     * @return \GuzzleHttp\Psr7\Response
     * @throws RemoteTemplateNotFoundException
     */
    private function fetchContentFromRemoteHost($url)
    {
        $options = [];
        $config = $this->config->get('aral.contentHost');
        if (null !== $config['auth_user'] && null !== $config['auth_password']) {
            $options = ['auth' => [$config['auth_user'], $config['auth_password'], $config['auth_type']]];
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
