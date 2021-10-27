<?php

namespace Schnoop\RemoteTemplate\View;

use Closure;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Schnoop\RemoteTemplate\Exceptions\IgnoredUrlSuffixException;
use Schnoop\RemoteTemplate\Exceptions\RemoteHostNotConfiguredException;
use Schnoop\RemoteTemplate\Exceptions\RemoteTemplateNotFoundException;
use Schnoop\RemoteTemplate\Exceptions\UrlIsForbiddenException;

/**
 * Class RemoteTemplateFinder.
 */
class RemoteTemplateFinder
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
    protected $config;

    /**
     * @var Closure[]
     */
    protected $handler;

    /**
     * @var Filesystem
     */
    protected $files;

    /**
     * @var Closure
     */
    protected $templateUrlCallback;

    /**
     * Create a new file view loader instance.
     *
     * @param Filesystem $files
     * @param Repository $config
     * @param Client $client
     */
    public function __construct(Filesystem $files, Repository $config, Client $client)
    {
        $this->client = $client;
        $this->files = $files;
        $this->config = $config;
        $this->remotePathDelimiter = $this->config->get('remote-view.remote-delimiter');
    }

    /**
     * Returns true if template is a remote resource.
     *
     * @param string $name Name of template
     *
     * @return bool
     */
    public function hasRemoteInformation($name): bool
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
     * @throws UrlIsForbiddenException
     */
    public function findRemotePathView($name): string
    {
        $name = trim(Str::replaceFirst($this->remotePathDelimiter, '', $name));

        $namespace = 'default';
        if ($this->hasNamespace($name) === true) {
            $elements = $this->parseRemoteNamespaceSegments($name);
            $namespace = $elements[0];
            $name = $elements[1];
        }

        $remoteHost = $this->getRemoteHost($namespace);

        // Check if URL suffix is ignored
        if ($this->urlHasIgnoredSuffix($name, $remoteHost) === true) {
            throw new IgnoredUrlSuffixException('URL # '.$name.' has an ignored suffix.');
        }

        // Check if URL is forbidden.
        if ($this->isForbiddenUrl($name, $remoteHost) === true) {
            throw new UrlIsForbiddenException('URL # '.$name.' is forbidden.', 404);
        }

        $url = $this->getTemplateUrlForIdentifier($name, $remoteHost);
        $url = $this->callModifyTemplateUrlCallback($url);

        $path = $this->getViewFolder($namespace);
        $path .= Str::slug($url).'.blade.php';
        if ($remoteHost['cache'] === true && $this->files->exists($path) === true) {
            return $path;
        }

        $url = rtrim($remoteHost['host'], '/').'/'.ltrim($url, '/');

        $content = $this->fetchContentFromRemoteHost($url, $remoteHost);
        if ($content instanceof Response === true) {
            $content = $content->getBody()->getContents();
        } elseif ($content instanceof \Illuminate\Http\Response) {
            $content = (string) $content->getContent();
        }
        $this->files->put($path, $content);

        return $path;
    }

    /**
     * Returns true if given url is forbidden.
     *
     * @param string $url
     * @param array $remoteHost
     *
     * @return bool
     */
    private function isForbiddenUrl($url, $remoteHost): bool
    {
        $ignoreUrlSuffix = $this->config->get('remote-view.ignore-urls');
        if (isset($remoteHost['ignore-urls']) === true && is_array($remoteHost['ignore-urls']) === true) {
            $ignoreUrlSuffix = array_merge($ignoreUrlSuffix, $remoteHost['ignore-urls']);
        }

        $parsedUrl = parse_url($url, PHP_URL_PATH);

        return in_array(pathinfo($parsedUrl, PATHINFO_DIRNAME), $ignoreUrlSuffix, true)
            || in_array(pathinfo($parsedUrl, PATHINFO_BASENAME), $ignoreUrlSuffix, true);
    }

    /**
     * Check for valid namespace.
     *
     * @param string $name
     *
     * @return bool
     */
    protected function hasNamespace($name): bool
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
     * @param string $name
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function parseRemoteNamespaceSegments($name): array
    {
        $segments = explode(FileViewFinder::HINT_PATH_DELIMITER, $name);
        if (count($segments) < 2) {
            throw new InvalidArgumentException("View [{$name}] has an invalid name.");
        }

        return $segments;
    }

    /**
     * Return array with remote host config.
     *
     * @param string $namespace
     *
     * @return array
     * @throws RemoteHostNotConfiguredException
     */
    protected function getRemoteHost($namespace): array
    {
        $config = $this->config->get('remote-view.hosts');
        if (isset($config[$namespace]) === false) {
            throw new RemoteHostNotConfiguredException('No remote host configured for namespace # '
                .$namespace.'. Please check your remote-view.php config file.');
        }

        return $config[$namespace];
    }

    /**
     * Returns true if given url is static.
     *
     * @param string $url
     * @param array $remoteHost
     *
     * @return bool
     */
    protected function urlHasIgnoredSuffix($url, $remoteHost): bool
    {
        $parsedUrl = parse_url($url, PHP_URL_PATH);
        $pathInfo = pathinfo($parsedUrl, PATHINFO_EXTENSION);

        $ignoreUrlSuffix = $this->config->get('remote-view.ignore-url-suffix');
        if (isset($remoteHost['ignore-url-suffix']) === true && is_array($remoteHost['ignore-url-suffix']) === true) {
            $ignoreUrlSuffix = array_merge($ignoreUrlSuffix, $remoteHost['ignore-url-suffix']);
        }

        return in_array($pathInfo, $ignoreUrlSuffix, true);
    }

    /**
     * Returns remote url for given $identifier.
     *
     * @param string $identifier
     * @param array $remoteHost
     *
     * @return string
     */
    protected function getTemplateUrlForIdentifier($identifier, $remoteHost): string
    {
        $route = $identifier;
        if (isset($remoteHost['mapping'][$identifier]) === true) {
            $route = $remoteHost['mapping'][$identifier];
        }
        if (strpos($route, '/') > 0) {
            return '/'.$route;
        }

        return $route;
    }

    /**
     * Call callback that will be called after template url has been set.
     *
     * @param string $url
     *
     * @return string
     */
    protected function callModifyTemplateUrlCallback(string $url): string
    {
        if ($this->templateUrlCallback !== null
            && is_callable($this->templateUrlCallback) === true
        ) {
            return call_user_func($this->templateUrlCallback, $url);
        }

        return $url;
    }

    /**
     * Get folder where fetched views will be stored.
     *
     * @param string $namespace
     *
     * @return string
     * @throws RuntimeException
     */
    protected function getViewFolder($namespace): string
    {
        $path = $this->config->get('remote-view.view-folder');
        $path = rtrim($path, '/').'/'.$namespace.'/';
        if (is_dir($path) === false) {
            if (! mkdir($path, 0777, true) && ! is_dir($path)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
            }
        }

        return $path;
    }

    /**
     * Fetch content from $url.
     *
     * @param string $url
     * @param array $remoteHost
     *
     * @return ResponseInterface|Response
     * @throws RemoteTemplateNotFoundException
     */
    public function fetchContentFromRemoteHost($url, $remoteHost)
    {
        $options = ['http_errors' => false];
        if (isset($remoteHost['request_options']) === true) {
            $options = array_merge($options, $remoteHost['request_options']);
        }
        try {
            $result = $this->client->get($url, $options);

            return $this->callResponseHandler($result, $remoteHost);
        } catch (Exception $e) {
            throw new RemoteTemplateNotFoundException($url, 404);
        }
    }

    /**
     * Call handler if any defined.
     *
     * @param ResponseInterface $result
     * @param array $remoteHost
     *
     * @return ResponseInterface|\Illuminate\Http\Response|Response
     */
    protected function callResponseHandler($result, array $remoteHost)
    {
        if (isset($this->handler[$result->getStatusCode()]) === true
            && is_callable($this->handler[$result->getStatusCode()]) === true
        ) {
            return call_user_func($this->handler[$result->getStatusCode()], $result, $remoteHost, $this);
        }

        return $result;
    }

    /**
     * Push a handler to the stack.
     *
     * @param int|array $statusCodes
     * @param callable $callback
     */
    public function pushResponseHandler($statusCodes, $callback)
    {
        foreach ((array) $statusCodes as $statusCode) {
            $this->handler[$statusCode] = $callback;
        }
    }

    /**
     * Set a callback that will be called after template url has been set.
     *
     * @param Closure $callback
     */
    public function setModifyTemplateUrlCallback($callback)
    {
        $this->templateUrlCallback = $callback;
    }
}
