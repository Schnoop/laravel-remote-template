<?php

declare(strict_types=1);

namespace Schnoop\RemoteTemplate\View;

use Closure;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\View\ViewFinderInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Schnoop\RemoteTemplate\Exceptions\IgnoredUrlSuffixException;
use Schnoop\RemoteTemplate\Exceptions\RemoteHostNotConfiguredException;
use Schnoop\RemoteTemplate\Exceptions\RemoteTemplateNotFoundException;
use Schnoop\RemoteTemplate\Exceptions\UrlIsForbiddenException;

class RemoteTemplateFinder
{
    /**
     * @var string
     */
    protected $remotePathDelimiter;

    /**
     * @var Closure[]
     */
    protected array $handler;

    /**
     * @var Closure
     */
    protected $templateUrlCallback;

    /**
     * @var Closure
     */
    protected $viewFilenameCallback;

    /**
     * Create a new file view loader instance.
     *
     * @param Filesystem $files
     * @param Repository $config
     * @param Client $client
     */
    public function __construct(
        protected Filesystem $files,
        protected Repository $config,
        protected Client $client
    ) {
        $this->remotePathDelimiter = $this->config->get('remote-view.remote-delimiter');
    }

    /**
     * Returns true if template is a remote resource.
     *
     * @param string $name Name of template
     */
    public function hasRemoteInformation(string $name): bool
    {
        return Str::startsWith($name, $this->remotePathDelimiter);
    }

    /**
     * Fetch template from remote resource, store locally and return path to local file.
     *
     * @param string $name Remote URL to fetch template from
     *
     * @throws IgnoredUrlSuffixException
     * @throws RemoteTemplateNotFoundException
     * @throws RemoteHostNotConfiguredException
     * @throws UrlIsForbiddenException
     * @throws GuzzleException
     */
    public function findRemotePathView(string $name): string
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
        $path .= $this->getViewFilename($url);
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
     * Fetch content from $url.
     *
     * @throws GuzzleException
     * @throws RemoteTemplateNotFoundException
     */
    public function fetchContentFromRemoteHost(
        string $url,
        array $remoteHost
    ): \Illuminate\Http\Response|Response|ResponseInterface {
        $options = [
            'http_errors' => false,
        ];
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
     * Push a handler to the stack.
     */
    public function pushResponseHandler(array|int $statusCodes, callable $callback): void
    {
        foreach ((array) $statusCodes as $statusCode) {
            $this->handler[$statusCode] = $callback;
        }
    }

    /**
     * Set a callback that will be called after template url has been set.
     */
    public function setModifyTemplateUrlCallback(Closure $callback): void
    {
        $this->templateUrlCallback = $callback;
    }

    /**
     * Set a callback that will be called to set the name of the view file.
     */
    public function setViewFilenameCallback(Closure $callback): void
    {
        $this->viewFilenameCallback = $callback;
    }

    /**
     * Check for valid namespace.
     */
    protected function hasNamespace(string $name): bool
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
     * @throws InvalidArgumentException
     */
    protected function parseRemoteNamespaceSegments(string $name): array
    {
        $segments = explode(ViewFinderInterface::HINT_PATH_DELIMITER, $name);
        if (\count($segments) < 2) {
            throw new InvalidArgumentException("View [{$name}] has an invalid name.");
        }

        return $segments;
    }

    /**
     * Return array with remote host config.
     *
     * @throws RemoteHostNotConfiguredException
     */
    protected function getRemoteHost(string $namespace): array
    {
        $config = $this->config->get('remote-view.hosts');
        if (isset($config[$namespace]) === false) {
            throw new RemoteHostNotConfiguredException(
                'No remote host configured for namespace # '.$namespace.'. Please check your remote-view.php config file.'
            );
        }

        return $config[$namespace];
    }

    /**
     * Returns true if given url is static.
     */
    protected function urlHasIgnoredSuffix(string $url, array $remoteHost): bool
    {
        $parsedUrl = parse_url($url, PHP_URL_PATH);
        $pathInfo = pathinfo($parsedUrl, PATHINFO_EXTENSION);

        $ignoreUrlSuffix = $this->config->get('remote-view.ignore-url-suffix');
        if (isset($remoteHost['ignore-url-suffix']) === true && \is_array($remoteHost['ignore-url-suffix']) === true) {
            $ignoreUrlSuffix = array_merge($ignoreUrlSuffix, $remoteHost['ignore-url-suffix']);
        }

        return \in_array($pathInfo, $ignoreUrlSuffix, true);
    }

    /**
     * Returns remote url for given $identifier.
     */
    protected function getTemplateUrlForIdentifier(string $identifier, array $remoteHost): string
    {
        $route = $identifier;
        if (isset($remoteHost['mapping'][$identifier]) === true) {
            $route = $remoteHost['mapping'][$identifier];
        }
        if (mb_strpos($route, '/') > 0) {
            return '/'.$route;
        }

        return $route;
    }

    /**
     * Call callback that will be called after template url has been set.
     */
    protected function callModifyTemplateUrlCallback(string $url): string
    {
        if ($this->templateUrlCallback !== null
            && \is_callable($this->templateUrlCallback) === true
        ) {
            return ($this->templateUrlCallback)($url);
        }

        return $url;
    }

    /**
     * Get folder where fetched views will be stored.
     *
     * @throws RuntimeException
     */
    protected function getViewFolder(string $namespace): string
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
     * Call handler if any defined.
     */
    protected function callResponseHandler(
        ResponseInterface $result,
        array $remoteHost
    ): \Illuminate\Http\Response|Response|ResponseInterface {
        if (isset($this->handler[$result->getStatusCode()]) === true
            && \is_callable($this->handler[$result->getStatusCode()]) === true
        ) {
            return \call_user_func($this->handler[$result->getStatusCode()], $result, $remoteHost, $this);
        }

        return $result;
    }

    protected function getViewFilename(string $url): string
    {
        if ($this->viewFilenameCallback !== null
            && \is_callable($this->viewFilenameCallback) === true
        ) {
            return ($this->viewFilenameCallback)($url);
        }

        return Str::slug($url).'.blade.php';
    }

    /**
     * Returns true if given url is forbidden.
     */
    private function isForbiddenUrl(string $url, array $remoteHost): bool
    {
        $ignoreUrlSuffix = $this->config->get('remote-view.ignore-urls');
        if (isset($remoteHost['ignore-urls']) === true && \is_array($remoteHost['ignore-urls']) === true) {
            $ignoreUrlSuffix = array_merge($ignoreUrlSuffix, $remoteHost['ignore-urls']);
        }

        $parsedUrl = parse_url($url, PHP_URL_PATH);

        return \in_array(pathinfo($parsedUrl, PATHINFO_DIRNAME), $ignoreUrlSuffix, true)
            || \in_array(pathinfo($parsedUrl, PATHINFO_BASENAME), $ignoreUrlSuffix, true);
    }
}
