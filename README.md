# Laravel Remote Template

`laravel-remote-template` is a package for fetching templates from a remote URL.


## Installation 

First, add our custom composer repository to your `composer.js`:

At first you have to add the custom composer repository to your projects composer.json:

```json
{
  "repositories": [{
    "type": "composer",
    "url": "http://aral-satis.dev71.antwerpes.de/"
  }]
}
```

Until we have HTTPS support, you will also have to add the following line to your configuration:

```
"config": {
  ...
  "secure-http": false
}
```

Afterwards, you can simply install the package with composer:

```shell
composer require antwerpes/laravel-remote-template
```

If you are using an older version of Laravel (< 5.5), you will also need to add our service provider
to your app configuration (`config/app.php`):

```php
'providers' => [
    ...
    Antwerpes\RemoteTemplate\RemoteTemplateServiceProvider::class,
],    
```

Next, publish the configuration files:

```shell
php artisan vendor:publish --provider="Antwerpes\RemoteTemplate\RemoteTemplateServiceProvider"
```

## Configuration

#### Configuring the remote delimiter

The package overwrites the default Laravel view finder class used to resolve views by name. When encountering a special remote delimiter token, the configured remote host will be used to resolve the view instead of the local filesystem.

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

```ph
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

