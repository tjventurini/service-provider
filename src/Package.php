<?php

namespace Tjventurini\ServiceProvider;

use ReflectionClass;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class Package
{
    /**
     * Slug of this package.
     *
     * @var string
     */
    private string $package_slug;

    /**
     * List of services with their configuration.
     *
     * @var array
     */
    private array $services = [
        // FooService::class => ['api_key' => 'asdf1234']
    ];

    /**
     * List of commands that should be registered.
     *
     * @var array
     */
    public array $commands = [
        // AppInstall::class
    ];

    /**
     * Does this package have a configuration dir and file?
     *
     * @var false
     */
    private bool $has_config = false;

    /**
     * Does this package have migrations?
     *
     * @var bool
     */
    private bool $has_migrations = false;

    /**
     * Does this package have views?
     *
     * @var bool
     */
    private bool $has_views = false;

    /**
     * Does this package have translations?
     *
     * @var bool
     */
    private bool $has_translations = false;

    /**
     * Does this package have a graphql schema file?
     *
     * @var bool
     */
    private bool $has_graphql_schema = false;

    /**
     * List of graphql namespaces to be setup.
     *
     * @var array
     */
    private array $graphql_namespaces = [
        // 'queries' => 'GraphQL\\\\Queries'
    ];

    /**
     * Does this package have a web routes file?
     *
     * @var bool
     */
    private bool $has_web_routes = false;

    /**
     * Does this package have a api routes file?
     *
     * @var bool
     */
    private bool $has_api_routes = false;

    /**
     * The route files of this package.
     *
     * @var array
     */
    private array $route_files = [];

    /**
     * Construct the package based on the service provider of the package to be set up.
     *
     * @param  Tjventurini\ServiceProvider\ServiceProvider $ServiceProvider
     * @return void
     */
    public function __construct(ServiceProvider $ServiceProvider)
    {
        $this->service_provider = $ServiceProvider;
    }

    /**
     * Autodetect the available entities to be setup.
     *
     * @return void
     */
    public function autodetect(): void
    {
        // should we setup the configuration?
        if ($this->configFileExists()) {
            $this->hasConfig();
        }

        // should we setup database migrations?
        if ($this->migrationsFolderExists()) {
            $this->hasMigrations();
        }

        // should we setup views?
        if ($this->viewsFolderExists()) {
            $this->hasViews();
        }

        // should we setup translations?
        if ($this->translationsFolderExists()) {
            $this->hasTranslations();
        }

        // should we setup a graphql schema file
        if ($this->graphQLSchemaFileExists()) {
            $this->hasGraphQLSchema();
        }

        // should we setup web routes
        if ($this->webRoutesFileExists()) {
            $this->hasWebRoutes();
        }

        // should we setup api routes
        if ($this->apiRoutesFileExists()) {
            $this->hasApiRoutes();
        }
    }

    /**
     * Get a reflection class instance of the Package class.
     *
     * @return ReflectionClass
     */
    private function getReflection(): ReflectionClass
    {
        // create an instance of the ReflectionClass in order
        //   to get the namespace of this package.
        $ReflectionClass = new ReflectionClass($this->service_provider);

        // return the ReflectionClass instance
        return $ReflectionClass;
    }

    /**
     * Method to get the package namespace from a given service provider.
     *
     * @return string
     */
    public function getPackageNamespace(): string
    {
        // get a ReflectionClass instance
        $ReflectionClass = $this->getReflection();

        // return the namespace
        return $ReflectionClass->getNamespaceName();
    }

    /**
     * Method to get the package slug of this package.
     *
     * @return string
     */
    public function getPackageSlug(): string
    {
        // if the package slug was set manually or
        // this method has been run already, just
        // return the package slug as available.
        if (isset($this->package_slug)) {
            return $this->package_slug;
        }

        // get the namespace to base the slug on.
        $namespace = $this->getPackageNamespace();

        // transform the namespace into a kebab string kinda slug.
        $package_name = explode('\\', $namespace)[1];
        $slug         = Str::kebab($package_name);

        // save the slug
        $this->package_slug = $slug;

        // return the created slug.
        return $slug;
    }

    /**
     * Set the package slug of this package.
     *
     * @param  string  $slug
     * @return Package
     */
    public function setPackageSlug(string $slug): self
    {
        // set the package slug
        $this->package_slug = $slug;

        // return current instance
        return $this;
    }

    /**
     * Method to get the package namespace from a given service provider.
     *
     * @return string
     */
    public function getEscapedPackageNamespace(): string
    {
        // get the namespace
        $namespace = $this->getPackageNamespace();

        // escape the namespace
        $namespace = str_replace('\\', '\\\\', $namespace);

        // return the escaped namespace string
        return $namespace;
    }

    /**
     * Method to get the package path related to this package.
     *
     * @return string
     */
    public function getPackagePath(): string
    {
        // check if there is a config folder available
        $ReflectionClass = $this->getReflection();

        // get the base path of the package
        $src_path     = dirname($ReflectionClass->getFileName());
        $package_path = substr($src_path, 0, strpos($src_path, DIRECTORY_SEPARATOR . 'src'));

        // return the package path
        return $package_path;
    }

    /**
     * Build the path to a subfolder of this package.
     *
     * @return string
     */
    private function buildPath(string $folder): string
    {
        // get the base path of the package
        $base_path = $this->getPackagePath();

        // add the given suffix
        $path = $base_path . DIRECTORY_SEPARATOR . $folder;

        // return the path
        return $path;
    }

    /**
     * Check if there is a config dir and file available.
     *
     * @return bool
     */
    private function configFileExists(): bool
    {
        // get the config path of this package
        $config_path = $this->getConfigPath();

        // get the config file path of the default configuration
        $config_file_path = $this->getConfigFilePath();

        // stop if the there is no config folder
        //   or if there is no matching config file available
        if (!File::isDirectory($config_path) || !File::exists($config_file_path)) {
            return false;
        }

        // return true if the config dir and file are available
        return true;
    }

    /**
     * Call this method if your package has a configuration file available
     * under /config/<package_slug>.php
     *
     * @return Package
     */
    public function hasConfig(): self
    {
        // overwrite the attribute
        $this->has_config = true;

        // return current instance
        return $this;
    }

    /**
     * Returns true if the package has a configuration available.
     *
     * @return bool
     */
    public function getHasConfig(): bool
    {
        return !!$this->has_config;
    }

    /**
     * Get the path to the config folder of this package.
     *
     * @return string
     */
    public function getConfigPath(): string
    {
        // build the path to the package config folder
        $config_path = $this->buildPath('config');

        // return the config path
        return $config_path;
    }

    /**
     * Get the path to the config file of this package.
     *
     * @return string
     */
    public function getConfigFilePath(): string
    {
        // get the package slug
        $slug = $this->getPackageSlug();

        // build the path to the package config file
        $config_file_path = $this->getConfigPath() . DIRECTORY_SEPARATOR . $slug . '.php';

        // return the generated config file path
        return $config_file_path;
    }

    /**
     * Call this method if your package has migrations available under
     * /database/migrations.
     *
     * @return Package
     */
    public function hasMigrations(): self
    {
        // overwrite the attribute
        $this->has_migrations = true;

        // return current instance
        return $this;
    }

    /**
     * Return true if the package has migrations.
     *
     * @return bool
     */
    public function getHasMigrations(): bool
    {
        return !!$this->has_migrations;
    }

    /**
     * Check if the package has a migrations folder under
     * /database/migrations.
     *
     * @return bool
     */
    private function migrationsFolderExists(): bool
    {
        // build the path to the migrations
        $migrations_path = $this->getMigrationsPath();

        // stop if there is no migrations folder
        if (!File::isDirectory($migrations_path)) {
            return false;
        }

        // return true if the folder is available
        return true;
    }

    /**
     * Get the path to the migrations of this package.
     *
     * @return string
     */
    public function getMigrationsPath(): string
    {
        // build the path the the migrations folder
        $migrations_path =  $this->buildPath('database/migrations');

        // return the path to the migrations
        return $migrations_path;
    }

    /**
     * Set the artisan commands of this package.
     *
     * @param  array   $commands
     * @return Package
     */
    public function hasCommands(array $commands): self
    {
        // set the commands to load
        $this->commands = $commands;

        // return current instance
        return $this;
    }

    /**
     * Get the list of artisan commands of this package.
     *
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Register a service of this package.
     *
     * @param  string           $Service
     * @param  mixed|null|array $config
     * @return Package
     */
    public function registerService(string $Service, $config = null): self
    {
        // add service and configuration to the list of services
        $this->services[$Service] = $config;

        // return current instance
        return $this;
    }

    /**
     * Get the list of services that should be registered.
     *
     * @return array
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * Check if the views folder exists under /resources/views.
     *
     * @return bool
     */
    private function viewsFolderExists(): bool
    {
        // build the path the the views
        $views_path = $this->getViewsPath();

        // stop if there is no folder for the views
        if (!File::isDirectory($views_path)) {
            return false;
        }

        // return true if the folder exists
        return true;
    }

    /**
     * Call this method if your package has views.
     *
     * @return Package
     */
    public function hasViews(): self
    {
        // overwrite attribute
        $this->has_views = true;

        // return current instance
        return $this;
    }

    /**
     * Returns true if the package has views.
     *
     * @return bool
     */
    public function getHasViews(): bool
    {
        return !!$this->has_views;
    }

    /**
     * Get the path to the views of this package.
     *
     * @return string
     */
    public function getViewsPath(): string
    {
        // build the path to the views
        $views_path = $this->buildPath('resources/views');

        // return the path to the views
        return $views_path;
    }

    /**
     * Check if the lang folder for translations exists under
     * /resources/lang.
     *
     * @return bool
     */
    private function translationsFolderExists(): bool
    {
        // build the path to the lang dir
        $lang_path = $this->getTranslationsFolderPath();

        // stop if there is no folder for the translations
        if (!File::isDirectory($lang_path)) {
            return false;
        }

        // return true if the folder exists
        return true;
    }

    /**
     * Return the path to the translations folder of this package.
     *
     * @return string
     */
    public function getTranslationsFolderPath(): string
    {
        // build the path to the translations folder
        $translations_path = $this->buildPath('resources/lang');

        // return the build path
        return $translations_path;
    }

    /**
     * Call this method if your package has translations.
     *
     * @return Package
     */
    public function hasTranslations(): self
    {
        // overwrite attribute
        $this->has_translations = true;

        // return current instance
        return $this;
    }

    /**
     * Returns true if this package has translations.
     *
     * @return bool
     */
    public function getHasTranslations(): bool
    {
        return !!$this->has_translations;
    }

    /**
     * Call this method if your package has a graphql file under
     * /graphql/schema.graphql.
     *
     * @return Package
     */
    public function hasGraphqlSchema(): self
    {
        // overwrite attribute
        $this->has_graphql_schema = true;

        // return current instance
        return $this;
    }

    /**
     * Returns true if we want to setup a graphql schema file.
     *
     * @return bool
     */
    public function getHasGraphQLSchema(): bool
    {
        return !!$this->has_graphql_schema;
    }

    /**
     * Returns true if there is a graphql schema file available
     * under /graphql/schema.graphql.
     *
     * @return bool
     */
    private function graphQLSchemaFileExists(): bool
    {
        // get the path the the graphql schema file
        $schema_file_path = $this->getGraphQLSchemaFilePath();

        // stop if the file is not present
        if (!File::exists($schema_file_path)) {
            return false;
        }

        // return true if there is a graphql schema file
        return true;
    }

    /**
     * Get the path to the graphql schema file.
     *
     * @return string
     */
    public function getGraphQLSchemaFilePath(): string
    {
        // build the path to the schema file
        $schema_file_path = $this->buildPath('graphql/schema.graphql');

        // return the path that we build
        return $schema_file_path;
    }

    /**
     * Setup the graphql namespaces to be setup.
     *
     * @param  array   $namespaces
     * @return Package
     */
    public function hasGraphQLNamespaces(array $namespaces): self
    {
        // save the graphql namespaces to be registered
        $this->graphql_namespaces = $namespaces;

        // return current instance
        return $this;
    }

    /**
     * Get the graphql namespaces to be registered for this package.
     *
     * @return array
     */
    public function getGraphQLNamespaces(): array
    {
        return $this->graphql_namespaces;
    }

    /**
     * Call this method if your package has web routes.
     *
     * @return Package
     */
    public function hasWebRoutes(): self
    {
        // overwrite attribute
        $this->has_web_routes = true;

        // register the default web routes file
        $this->registerRouteFile('routes/web.php');

        // return current instance
        return $this;
    }

    /**
     * Returns true if we want to setup a web routes.
     *
     * @return bool
     */
    public function getHasWebRoutes(): bool
    {
        return !!$this->has_web_routes;
    }

    /**
     * Returns true if there is web routes file under
     * routes/web.php
     *
     * @return bool
     */
    private function webRoutesFileExists(): bool
    {
        // get the path the the graphql schema file
        $web_routes_file_path = $this->getWebRoutesFilePath();

        // stop if the file is not present
        if (!File::exists($web_routes_file_path)) {
            return false;
        }

        // return true if there is a graphql schema file
        return true;
    }

    /**
     * Get the path to the web routes file.
     *
     * @return string
     */
    public function getWebRoutesFilePath(): string
    {
        // build the path to the web routes file
        $web_routes_file_path = $this->buildPath('routes/web.php');

        // return the path that we build
        return $web_routes_file_path;
    }

    /**
     * Call this method if your package has api routes.
     *
     * @return Package
     */
    public function hasApiRoutes(): self
    {
        // overwrite attribute
        $this->has_api_routes = true;

        // register the default api routes file
        $this->registerRouteFile('routes/api.php');

        // return current instance
        return $this;
    }

    /**
     * Returns true if we want to setup a api routes.
     *
     * @return bool
     */
    public function getHasApiRoutes(): bool
    {
        return !!$this->has_api_routes;
    }

    /**
     * Returns true if there is api routes file under
     * routes/api.php
     *
     * @return bool
     */
    private function apiRoutesFileExists(): bool
    {
        // get the path the the graphql schema file
        $api_routes_file_path = $this->getApiRoutesFilePath();

        // stop if the file is not present
        if (!File::exists($api_routes_file_path)) {
            return false;
        }

        // return true if there is a graphql schema file
        return true;
    }

    /**
     * Get the path to the api routes file.
     *
     * @return string
     */
    public function getApiRoutesFilePath(): string
    {
        // build the path to the api routes file
        $api_routes_file_path = $this->buildPath('routes/api.php');

        // return the path that we build
        return $api_routes_file_path;
    }

    /**
     * Return true if there is at least one route file.
     *
     * @return bool
     */
    public function getHasRoutes(): bool
    {
        return !!count($this->route_files);
    }

    /**
     * Register a given route file.
     *
     * @param  string                $route_file
     * @return Package
     * @throws FileNotFoundException
     */
    public function registerRouteFile(string $route_file): self
    {
        // build the full path
        $full_path_to_the_route_file = $this->buildPath($route_file);

        // throw an error if the file does not exist
        if (!File::exists($full_path_to_the_route_file)) {
            throw new FileNotFoundException($full_path_to_the_route_file . ' could not be found.');
        }

        // save the route file for to be registered
        $this->route_files[] = $full_path_to_the_route_file;

        // return current instance
        return $this;
    }

    /**
     * Return the array of route files that we want to register.
     *
     * @return array
     */
    public function getRouteFiles(): array
    {
        return $this->route_files;
    }
}
