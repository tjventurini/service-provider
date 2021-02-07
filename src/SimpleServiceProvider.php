<?php

namespace Tjventurini\ServiceProvider;

use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class SimpleServiceProvider extends ServiceProvider
{
    /**
     * The package to be handled.
     *
     * @var Package
     */
    protected Package $package;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // call method before we start registering the package
        $this->registeringPackage();

        // setup a Package instance
        $this->package = new Package();

        // configure the package
        $this->configurePackage($this->package);

        // setup the configuration of the package
        $this->setupConfig();

        // setup the facades of the package
        $this->setupServices();

        // setup the graphql namespaces and schema file
        //   of the package
        $this->setupGraphQL();

        // call this method when the package has been registered
        $this->packageRegistered();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // call this method before the package gets booted
        $this->bootingPackage();

        // setup the migrations of the package related
        // to the service provider instance.
        $this->setupMigrations();

        // setup the artisan commands of the package related
        // to the service provider instance.
        $this->setupCommands();

        // setup the views of the package related
        // to the service provider instance.
        $this->setupViews();

        // setup the translations of the package related
        // to the service provider instance.
        $this->setupTranslations();

        // call this method when we are done booting the package
        $this->packageBooted();
    }

    /**
     * Setup the configuration for the given package.
     *
     * @param  Package $Package
     * @return void
     */
    private function configurePackage(Package $Package): void
    {
        $Package->autodetect();
    }

    /**
     * Setup configuration using the Package instance.
     *
     * @return void
     */
    private function setupConfig()
    {
        // stop if we should not setup a configuration
        if (!$this->package->getHasConfig()) {
            return;
        }

        // get the package slug
        $slug = $this->package->getPackageSlug();

        // setup the configuration files to be publishable
        $this->publishes([
            $this->package->getConfigPath() => config_path(),
        ], $slug . '-config');

        // setup the configuration of the given package to be merged into
        // the laravel configuration.
        $this->mergeConfigFrom($this->package->getConfigFilePath(), $slug);
    }

    /**
     * Setup migrations in boot method.
     *
     * @return void
     */
    private function setupMigrations()
    {
        // stop if not running in console
        if (!$this->app->runningInConsole()) {
            return;
        }

        // stop if the package does not have migrations
        if (!$this->package->getHasMigrations()) {
            return;
        }

        // get the path to the migrations of this package
        $migrations_path = $this->package->getMigrationsPath();

        // setup the migrations of the given package
        $this->loadMigrationsFrom($migrations_path);
    }

    /**
     * Setup commands in boot method.
     *
     * @return void
     */
    private function setupCommands(): void
    {
        // stop if not running in console
        if (!$this->app->runningInConsole()) {
            return;
        }

        // get the artisan commands of this package
        $commands = $this->package->getCommands();

        // stop if there are no commands available
        if (!count($commands)) {
            return;
        }

        // setup the commands
        $this->commands($commands);
    }

    /**
     * Setup the services of the package.
     *
     * @return void
     */
    private function setupServices()
    {
        // loop through the services of the package and set them up
        collect($this->package->getServices())->each(function ($config, $Service) {
            $this->app->singleton(Str::slug($Service), function ($app) use ($Service, $config) {
                // if the config is not null, pass it to the service
                if ($config) {
                    return new $Service($config);
                }

                // if there is no config available, initialize the service without
                return new $Service;
            });
        });
    }

    /**
     * Setup the views of the package.
     *
     * @return void
     */
    private function setupViews(): void
    {
        // stop if the package does not have views
        if (!$this->package->getHasViews()) {
            return;
        }

        // get the package slug
        $slug = $this->package->getPackageSlug();

        // build the path the the views
        $views_path = $this->package->getViewsPath();

        // setup the views
        $this->loadViewsFrom($views_path, $slug);

        // make the views publishable
        $this->publishes([
            $views_path => resource_path('views/vendor/' . $slug)
        ], $slug . '-views');
    }

    /**
     * Setup the translation files of the package related
     * the the given service provider.
     *
     * @return void
     */
    private function setupTranslations(): void
    {
        // stop if this package does not have translations
        if (!$this->package->getHasTranslations()) {
            return;
        }

        // get the package slug
        $slug = $this->package->getPackageSlug();

        // get the path to the translations
        $translations_path = $this->package->getTranslationsFolderPath();

        // setup the lang
        $this->loadTranslationsFrom($translations_path, $slug);
    }

    /**
     * Setup graphql namespaces and schema of the given package.
     *
     * @return void
     */
    private function setupGraphQL(): void
    {
        // stop if lighthouse is not installed
        if (!class_exists(\Nuwave\Lighthouse\LighthouseServiceProvider::class)) {
            return;
        }

        // register the schema if present
        $this->registerGraphQLSchema();

        // register graphql namespaces
        $this->registerGraphQLNamespaces();
    }

    /**
     * Register the schema.graphql file if present.
     *
     * @return void
     */
    private function registerGraphQLSchema(): void
    {
        // stop if this package does not have a graphql schema file
        if (!$this->package->getHasGraphQLSchema()) {
            return;
        }

        // build the path to the schema file
        $schema_file_path = $this->package->getGraphQLSchemaFilePath();

        // register the schema file through the lighthouse event
        app('events')->listen(
            \Nuwave\Lighthouse\Events\BuildSchemaString::class,
            function () use ($schema_file_path): string {
                $stitcher = new \Nuwave\Lighthouse\Schema\Source\SchemaStitcher($schema_file_path);
                return $stitcher->getSchemaString();
            }
        );
    }

    /**
     * Register the graphql namespaces of the given package in
     * the lighthouse configuration.
     *
     * @return void
     */
    private function registerGraphQLNamespaces(): void
    {
        // get the graphql namespaces to be registered
        $namespaces = $this->package->getGraphQLNamespaces();

        // stop if there are no graphql namespaces to be setup
        if (!count($namespaces)) {
            return;
        }

        // save the given graphql namespaces in the lighthouse configuration
        $lighthouse_namespaces = collect(config('lighthouse.namespaces'));
        collect($namespaces)->each(function ($namespace, $type) use ($lighthouse_namespaces) {
            // stop if the type does not exist as key in the lighthouse configuration
            if (!$lighthouse_namespaces->contains($type)) {
                return;
            }

            // save the namespace to the lighthouse configuration
            config([
                'lighthouse.namespaces.' . $type => array_merge(
                    (array) config('lighthouse.namespaces.' . $type),
                    (array) $this->package->getEscapedPackageNamespace() . '\\\\' . $namespace
                )
            ]);
        });
    }

    /**
     * Method that is called before we start registering the package in
     * the register method of this class.
     *
     * @return void
     */
    public function registeringPackage(): void
    {
        // do something
    }

    /**
     * Method that is called after we registered the package in
     * the register method of this class.
     *
     * @return void
     */
    public function packageRegistered(): void
    {
        // do something
    }

    /**
     * Method that is called before we start booting the package in
     * the boot method of this class.
     *
     * @return void
     */
    public function bootingPackage(): void
    {
        // do something
    }

    /**
     * Method that is called after we booted the package in
     * the boot method of this class.
     *
     * @return void
     */
    public function packageBooted(): void
    {
        // do something
    }
}
