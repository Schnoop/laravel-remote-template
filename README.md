# Laravel Remote Template


A Laravel package to fetch templates from a remote URL.


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
composer require antwerpes/laravel-remote-template
```

Laravel 5.5 uses Package Auto-Discovery, so doesn't require you to manually add the ServiceProvider. 

### Laravel 5.5+:

If you don't use auto-discovery, add the ServiceProvider to the providers array in config/app.php

```php
Antwerpes\RemoteTemplate\RemoteTemplateServiceProvider::class,
```

If you want to use the facade, add this to your facades in app.php:

Copy the package config to your local config with the publish command:

```shell
php artisan vendor:publish --provider="Antwerpes\RemoteTemplate\RemoteTemplateServiceProvider"
```

Next you have to add your content host(s) to the published "remote-view.php" config file located in your laravel config folder. Please read the comments carefully.

### Usage
