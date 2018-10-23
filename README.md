[![pipeline status](https://doccheck.githost.io/aral-aktiengesellschaft/aral-ingenico-api-laravel/badges/develop/pipeline.svg)](https://doccheck.githost.io/aral-aktiengesellschaft/aral-ingenico-api-laravel/commits/develop)
[![coverage report](https://doccheck.githost.io/aral-aktiengesellschaft/aral-ingenico-api-laravel/badges/develop/coverage.svg)](https://doccheck.githost.io/aral-aktiengesellschaft/aral-ingenico-api-laravel/commits/develop)

# Laravel Ingenico LS API


This is a package to integrate the Ingenico LS API in a Laravel 5 project.

## Installation 

At first you have to add the custom composer repository to your projects composer.json:

```json
{
  "repositories": [{
    "type": "composer",
    "url": "http://aral-satis.dev71.antwerpes.de/"
  }]
}
```

Require this package with composer.

```shell
composer require antwerpes/ingenico-webservice-laravel
```

Laravel 5.5 uses Package Auto-Discovery, so doesn't require you to manually add the ServiceProvider. 

### Laravel 5.5+:

If you don't use auto-discovery, add the ServiceProvider to the providers array in config/app.php

```php
Antwerpes\Aral\IngenicoLsApi\LsApiServiceProvider::class,
```

If you want to use the facade, add this to your facades in app.php:

```php
'IngenicoLsApi' => Antwerpes\Aral\IngenicoLsApi\Facade::class,
```

Copy the package config to your local config with the publish command:

```shell
php artisan vendor:publish --provider="Antwerpes\Aral\IngenicoLsApi\LsApiServiceProvider"
```

Next you have to add the necessary environment variables to you locale .env file:

```ini
INGENICO_HOST=""
INGENICO_DEFAULT_ORIGINATOR=""
INGENICO_DEFAULT_SECRETKEY=""
INGENICO_CRYPTER_SALT=""
INGENICO_XML_DESTINATION=
```

### Executing request against the API

As we are in the Laravel context there is a facade we can use:

```php
IngenicoLsApi::dispatch(....);
```

Every request will be executed using the dispatch method. e.g

```php
$result = IngenicoLsApi::dispatch(new \Antwerpes\Aral\IngenicoLSApi\Service\Auth\ConsumerLogin('foo@bar.de', 'foobar'));
$result = IngenicoLsApi::dispatch(new \Antwerpes\Aral\IngenicoLSApi\Service\Account\GetDetails('my_account_token'));
```

If you don't like to use the facade, inject the Container into your method:

```php

use \Antwerpes\Aral\IngenicoLSApi\Service\Container;

public function hurz(Container $container)
{
    $result = $container->dispatch(new \Antwerpes\Aral\IngenicoLSApi\Service\Auth\ConsumerLogin('foo@bar.de', 'foobar'));
    $result = $container->dispatch(new \Antwerpes\Aral\IngenicoLSApi\Service\Account\GetDetails('my_account_token'));
}
```

### Configure multiple credentials.

Sometimes it is necessary to configure multiple credentials to the LS API. In this case you can easily add those to the credentials array in ingenico.php file located in the config folder.

 ```php

/*
|--------------------------------------------------------------------------
| Credentials
|--------------------------------------------------------------------------
|
| Is it possible to configure as many credentials as need. Those credentials will
| be available as method on the container. e.g. $container->consumer()->.... if you have
| configured a set with the key "consumer" below. There has to be a key "default".
|
*/

'credentials' => [

    'default' => [

        'originator' => env('INGENICO_DEFAULT_ORIGINATOR'),

        'secret_key' => env('INGENICO_DEFAULT_SECRETKEY'),

    ],

    // My new credentials.
    'company' => [

        'originator' => env('INGENICO_COMPANY_ORIGINATOR'),

        'secret_key' => env('INGENICO_COMPANY_SECRETKEY'),

    ],

]
 ```
 
If you like to send a request using the credentials configured under the "company" key you have to call the name of the key as method before dispatch.
 
```php
// Use default credentials
IngenicoLsApi::dispatch(....);

// Use company credentials
IngenicoLsApi::company()->dispatch(....);
```
 
It is also possible to change the default credentials if needed. Take a look at the config file:

```php
/*
|--------------------------------------------------------------------------
| Which credentials should be used as default. See configured credentials below
|--------------------------------------------------------------------------
|
*/

'default_credentials' => 'default',
```
