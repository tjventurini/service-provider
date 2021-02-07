# Service Provider (BETA)

![logo](./logo.png)

This package provides a the `SimpleServiceProvider` class that you can use to speed up your laravel package development.

This package was not inspired but highly influenced by [spatie/lravel-package-tools](https://github.com/spatie/laravel-package-tools) 😉

## Installation

To install this package you can run the following.

```
composer require tjventurini/service-provider
```

In order to use it in a package you should add it as a dependency in your `composer.json` file.

## Features

* Automatically detect resources provided by your package 🔍
* Can handle and autodetect configuration
* Can handle and autodetect migrations
* Can handle and autodetect translations
* Can handle and autodetect views
* Can handle artisan commands
* Can handle services
* Can handle and autodetect graphql schema 
* Can handle graphql namespaces

## Usage

To use the `SimpleServiceProvider` class just extend it on your `ServiceProvider`.

```php
use Tjventurini\ServiceProvider\SimpleServiceProvider;

class YourPackageServiceProvider extends SimpleServiceProvider
```

Per default the service provider will try to detect and setup the resources provided by your package without further steps required from you. If you want or need more flexibility for your setup, read on.

## Configuration

```php
use Tjventurini\ServiceProvider\SimpleServiceProvider;

class YourPackageServiceProvider extends SimpleServiceProvider
{
    /**
     * Setup the configuration for the given package.
     *
     * @param  Package $Package
     * @return void
     */
    public function configurePackage(Package $Package): void
    {
        $Package
            ->setPackageSlug('your-package-slug')
            ->hasConfig()
            ->hasMigrations()
            ->hasTranslations()
            ->hasCommands([
                SomeCommand::class,
                AnotherCommand::class
            ])
            ->hasGraphQLSchema()
            ->hasGraphQLNamespaces([
                'models' => 'Foo\\Bar'
            ])
            ->registerService(SomeService::class)
            ->registerService(ServiceWithConfig::class, ['api_key' => 'some-key']);
    }
}
```

For more information check out the [SimpleServiceProvider](src/SimpleServiceProvider.php) and [Package](src/Package.php) classes 🕵

## Roadmap 🛣

* Handle routes (web and api)
* Autodetect commands
* Autodetect services
* Autodetect graphql namespaces
* Enable handling of multiple configuration files
* Enable handling of multiple graphql schema files