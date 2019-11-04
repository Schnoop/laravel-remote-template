# Laravel Remote Template

[![Latest Version](https://img.shields.io/github/release/schnoop/laravel-remote-template.svg?style=flat-square)](https://github.com/schnoop/laravel-remote-template/releases)
[![Build Status](https://travis-ci.org/Schnoop/laravel-remote-template.svg?branch=develop)](https://travis-ci.org/Schnoop/laravel-remote-template)
[![Quality Score](https://img.shields.io/scrutinizer/g/schnoop/laravel-remote-template.svg?style=flat-square)](https://scrutinizer-ci.com/g/schnoop/laravel-remote-template)
[![StyleCI](https://github.styleci.io/repos/205439072/shield?branch=develop)](https://github.styleci.io/repos/205439072)
[![Total Downloads](https://img.shields.io/packagist/dt/schnoop/laravel-remote-template.svg?style=flat-square)](https://packagist.org/packages/schnoop/laravel-remote-template)

`laravel-remote-template` is a package for fetching blade templates from a remote URL.

## What is the use case for fetching content from a remote url?

Imagine your customer wants you to build a fully flexible application but also would like to manage the content by themself. Laravel is great for building applications - but managing content is not the focus.
Maybe you have expirences in Content Management System - but hey, those aren't as flexible as Laravel in building applications.
Why not use both? A CMS for the content and Laravel for the application. This package helps you to use content that is remote available for rendering in Laravel applications.

- [Installation](#installation)
- [Configuration](#configuration)
  - [Configuring the remote delimiter](#configuring-the-remote-delimiter)
  - [Configuring the view folder](#configuring-the-view-folder)
  - [Configuring the remote host](#configuring-the-remote-host)
  - [Configuring the URL mappings](#configuring-the-url-mappings)
  - [Configuring remote URls that should be ignored](#configuring-remote-urls-that-should-be-ignored)
  - [Configuring remote URls suffixes that should be ignored](#configuring-remote-urls-suffixes-that-should-be-ignored)
  - [Other configuration options](#other-configuration-options)
- [Using a fallback route](#using-a-fallback-route)
- [Modify remote URL before call is executed](#modify-remote-url-before-call-is-executed)
- [Push response handlers](#push-response-handlers)

## Installation 

Simply install the package with composer:

```shell
composer require schnoop/laravel-remote-template
```

If you are using an older version of Laravel (< 5.5), you will also need to add our service provider
to your app configuration (`config/app.php`):

```php
'providers' => [
    ...
    Schnoop\RemoteTemplate\RemoteTemplateServiceProvider::class,
],    
```

Next, publish the configuration files:

```shell
php artisan vendor:publish --provider="Schnoop\RemoteTemplate\RemoteTemplateServiceProvider"
```

## Configuration

#### Configuring the remote delimiter

The package extends Laravels FileViewFinder class used to resolve views by name. When encountering a special remote delimiter token, the configured remote host will be used to resolve the view instead of the local filesystem.

```php
'remote-delimiter' => 'remote:',
```

Now, everytime you call or include a view by name and the name starts with `remote:`, the template is fetched from the remote host. Example:

```php
@extends('remote:login')

{{-- Content --}}
@section('content')
	...
@endsection    
```

The following would still resolve the view using the default Laravel (filesystem) behaviour:

```php
@extends('layouts.master')

{{-- Content --}}
@section('content')
	...
@endsection   
```

#### Configuring the view folder

When resolving the remote templates, the files are downloaded to a special view folder. The view folder can be customized via the `view-folder` configuration option. If caching is enabled, these files will be re-used for subsequent requests and will not fire new requests to the remote host.

#### Configuring the remote host

You can configure multiple remote hosts with unique identifiers using the `hosts` configuration option. The identifier can then be appended to the remote delimiter.

```php
'hosts' => [
	'base' => [
    	...
    ],
],
```
The following example will use the `base` remote host to resolve the template:
```php
@extends('remote:base::login')

{{-- Content --}}
@section('content')
	...
@endsection   
```

If a `default` host is specified, it will be used when no other namespace is used. The following example will use the `default` host:

```php
@extends('remote:login')

{{-- Content --}}
@section('content')
	...
@endsection   
```

The host base URL can defined using the `hosts.*.host` configuration option:

```php
'hosts' => [
        'default' => [
            'host' => env('CONTENT_DOMAIN'),
        ],
    ],
```

#### Configuring the URL mappings

Any token following the remote delimiter and host identifier will be used to construct the URL for the remote host. In the example above, a request would be made to `www.yourhost.com/login`, and the response would be used as the resolved view file. If you wish to configure custom mappings, you can do so using the `mappings` configuration option:

```php
'hosts' => [
        'default' => [
            ...
            'mapping' => [
                'login' => '/index.php?id=51',
            ],
        ],
    ],
```

#### Configuring remote URls that should be ignored

If you have URLs that you don't want to expose via these fallback, you can configure those in the config file;

- `ignore-urls`: Is an array that can hold multiple urls that should not be resolved via the content host.

```php
'ignore-urls' => [
    'foo'
],
```

If the request url to the remote host starts with any configured `ignore-urls`, you will receive an UrlIsForbiddenException instead of content:

e.g: 

- http://remote-content-host.dev/foo/
- http://remote-content-host.dev/foo
- http://remote-content-host.dev/foo/bar
- http://remote-content-host.dev/foo/index.php

#### Configuring remote URls suffixes that should be ignored

Instead of configuring URLs starting with an particular string, you also can deny access to urls that end with a suffix:

```php
'ignore-url-suffix' => [
    'png',
    'jpg',
    'jpeg',
    'css',
    'js',
    'woff',
    'ttf',
    'gif',
    'svg'
],
```

In the case above we are denying the request to any static file.

#### Other configuration options

- `hosts.*.cache`: When set to true, requests to the remote host are only made if no matching template could be found in the view folder. If a template is found, it will be re-used for resolving the view.
- `hosts.*.request_options`: Array of request options that will be passed to the Guzzle HTTP client when making the request to the remote host. This option can be used to configure authentication.

## Using a fallback route

By using a fallback route in combination with a default view, any requests that do not match a route defined in Laravel will instead be forwarded to any of the configured remote hosts.

In your `routes/web.php` file:

```php
Route::fallback('FallbackController@fallback');
```

The controller would then simply call a view and pass the requesting URL:

```php
class FallbackController extends Controller
{
    /**
     * Fallback.
     *
     * @param Request $request
     *
     * @return View
     */
    public function fallback(Request $request): View
    {
        return view('cms::fallback')->with('uri', $request->getRequestUri());
    }
}
```

And the view would pass the URL to the remote host:

```php
@extends('remote:'.$uri)
```

Now, for any requests made to routes not defined in the application, a request will be made to the remote host. If a successful response is returned, it will be used as the view. Otherwise a `404` response will be returned. 



## Modify remote URL before call is executed

Someday, you will have the case, that you would like to force the remote host to render the template based on a state in your Laravel application. A very common case is definitely to change the navigation if a user is authenticated.

To achieve this, we have a callback that will be triggered right before the call to the remote host happens:

```php
$this->app->make('remoteview.finder')->setModifyTemplateUrlCallback(function ($url) {
    return $url;
});
```

In this callback, you have the chance to modify the request url as needed to tell the remote host to change its template rendering:

```php
$this->app->make('remoteview.finder')->setModifyTemplateUrlCallback(function ($url) {
    $glue = '?';
    if (strpos($url, $glue) !== false) {
        $glue = '&';
    }

    if (Auth::check() === true) {
        $url .= $glue . 'login=true';
    }

    // ..... following by more role checks e.g.

    return $url;
});
```

## Push response handlers

Last but not least you have the option to push handlers that will be executed after the call has happened:
Those handlers are assigned to  response codes, that the remote host returns:

In the following example the handler will be executed only if the remote host respond with a 301 HTTP status code.
```php
$this->app->make('remoteview.finder')->pushResponseHandler(301, function (Response $result, array $config, RemoteTemplateFinder $service) {
    // Do some stuff, and return the HTML.
});
```

A common case is that maybe the remote host responds with 301, which is a redirect to another url. In this case we would like to parse the destination out of the response, and fetch the content from there.
To achieve this, an instance of RemoteTemplateFinder is injected in the function that can be used to execute further calls.

```php
$this->app->make('remoteview.finder')->pushResponseHandler(301, function (Response $result, array $config, RemoteTemplateFinder $service) {
    return $service->fetchContentFromRemoteHost($result->getHeaderLine('Location'), $config);
});
```
