# Nette DI

Configure your Orisai CMF/Nette application

## Content

- [Setup](#setup)
- [Configurator](#configurator)
	- [Configuration](#configuration)
	- [Debug mode](#debug-mode)
		- [At localhost](#at-localhost)
		- [With env variable](#with-env-variable)
		- [With cookie](#with-cookie)
	- [Parameters](#parameters)
		- [Dynamic parameters](#dynamic-parameters)
		- [Predefined parameters](#predefined-parameters)
		- [Load parameters from env variables](#load-parameters-from-env-variables)
	- [Testing mode](#testing-mode)
	- [Import services](#import-services)
	- [Compilation](#compilation)
	- [Cache warmup](#cache-warmup)
- [Definitions loader](#definitions-loader)
- [Service manager](#service-manager)

## Setup

Install with [Composer](https://getcomposer.org)

```sh
composer require orisai/nette-di
```

## Configurator

An alternative for [nette/bootstrap](https://github.com/nette/bootstrap)

- none of the extensions is loaded by default
- debug mode is disabled unless you enable it

To use it, create bootstrap class where you preconfigure your application

```php
namespace App;

use OriNette\DI\Boot\Environment;
use OriNette\DI\Boot\ManualConfigurator;
use function dirname;

final class Bootstrap
{

	public static function boot(): ManualConfigurator
	{
		$configurator = new ManualConfigurator(dirname(__DIR__));

		$configurator->addStaticParameters(Environment::loadEnvParameters());

		$configurator->setDebugMode(
			Environment::isEnvDebug()
			|| Environment::isLocalhost()
			|| Environment::hasCookie(self::getDebugCookieValues()),
		);
		$configurator->enableDebugger();

		$configurator->addConfig(__DIR__ . '/wiring.neon');
		$configurator->addConfig(__DIR__ . '/../config/local.neon');

		return $configurator;
	}

	/**
	 * @return array<string>
 	 */
	private static function getDebugCookieValues(): array
	{
		return [];
	}

}
```

In application entrypoint (`index.php`) - get the configurator, create a container, get the application and run it.

```php
use App\Bootstrap;
use Nette\Application\Application;

require __DIR__ . '/../vendor/autoload.php';

Bootstrap::boot()
	->createContainer()
	->getByType(Application::class)
	->run();
```

### Configuration

Add config files

```php
$configurator->addConfig(__DIR__ . '/../config/local.neon');
```

By default, `neon` and `php` files supported. For other formats, add an own adapter:

```php
$configurator->addConfigAdapter('json', new JsonAdapter());
```

### Debug mode

Set debug mode

```php
$configurator->setDebugMode($condition);
```

After you set debug mode, enable debugger

- Requires [Tracy](https://github.com/nette/tracy) to be installed

```php
$configurator->enableDebugger();
```

If debug mode is enabled and any of configuration files or services changes, container regenerates.

#### At localhost

```php
use OriNette\DI\Boot\Environment;

$configurator->setDebugMode(Environment::isLocalhost());
```

#### In console

```php
use OriNette\DI\Boot\Environment;

$configurator->setDebugMode(Environment::isConsole());
```

Preferably use [env variable](#with-env-variable) for your local console, otherwise debug mode will be enabled in
console also on production.

#### With env variable

Useful for enabling debug mode in console

Set env variable to `true` or `1`

```sh
# Set env variable in console for current session
export ORISAI_DEBUG=true
```

Check env variable in bootstrap

```php
use OriNette\DI\Boot\Environment;

$configurator->setDebugMode(Environment::isEnvDebug());
```

We can also change the variable name to something else

```php
use OriNette\DI\Boot\Environment;

Environment::isEnvDebug('VARIABLE_NAME');
```

#### With cookie

Set debug cookie in your browser

`orisai-debug = really_long_and_secure_cookie_value`

Check cookie value in bootstrap

```php
use OriNette\DI\Boot\Environment;

$configurator->setDebugMode(Environment::hasCookie([
	'really_long_and_secure_cookie_value',
	'another_really_long_and_secure_cookie_value',
]));
```

*Be paranoid and always generate long cookie values like 30+ characters long*

We can also change the cookie name to something else

```php
use OriNette\DI\Boot\Environment;

Environment::hasCookie($cookieValues, 'cookie-name');
```

List of cookie values can be easily obtained from an env variable

- By default is expected env variable `DEBUG_COOKIE_VALUES` with values separated by a comma
	- `DEBUG_COOKIE_VALUES = val1,val2`
- Spaces around value and empty values are safely removed

```php
use OriNette\DI\Boot\CookieGetter;
use OriNette\DI\Boot\Environment;

Environment::hasCookie(CookieGetter::fromEnv());
```

### Parameters

Add parameters which can be used

```php
$configurator->addStaticParameters([
	'parameter' => 'value',
]);
```

When a static parameter is added, removed or it's value changes then container is re-generated.

#### Dynamic parameters

Dynamic parameter value can be changed each request. Only when parameter is added or removed. then container is
re-generated.

```php
$configurator->addDynamicParameters([
	'parameter' => 'value',
]);
```

#### Predefined parameters

Configurator define some parameters you may need:

- `%rootDir%`
	- base path of your app
- `%appDir%`
	- source code path
	- defaults to `%rootDir%/src`
- `%buildDir%`
	- permanently stored cache files
	- defaults to `%rootDir%/var/build`
- `%dataDir%`
	- uploaded data
	- defaults to `%rootDir%/data`
- `%logDir%`
	- log files
	- defaults to `%rootDir%/var/log`
- `%tempDir%`
	- temporarily stored cache files
	- defaults to `%rootDir%/var/tmp`
- `%vendorDir%`
	- third-party source code
	- defaults to `%rootDir%/vendor`
- `%wwwDir%`
	- public directory, should be the only one accessible via webserver - defaults to `%rootDir%/public`
- `%debugMode%`
	- whether application is in debug mode
- `%consoleMode%`
	- whether application is in console mode
- `%container%`
	- info about container - when it was compiled, name of the container

#### Load parameters from env variables

Load environment variables and transform them into an array of parameters.

```php
use OriNette\DI\Boot\Environment;

$configurator->addStaticParameters(Environment::loadEnvParameters());
```

Env variables are transformed into array via pattern `PREFIX{delimiter}{NAME-1}{delimiter}{NAME-N}`.

- default prefix is `ORISAI` and delimiter is `__`

In following example is how env variable look and the resulting parameters after transformation

```dotenv
ORISAI__PARAMETER = parameter
ORISAI__SINGLE_UNDERSCORE = single_underscore
ORISAI__UPPER__lower__MiXeD = upper.lower.mixed
ORISAI__UPPER__another__parameter = upper.another.parameter
```

```neon
parameters:
	parameter: parameter
	single_underscore: single_underscore
	upper:
		lower:
			mixed: upper.lower.mixed
		another:
			parameter: upper.another.parameter
```

We can can also change delimiter to e.g. `:` and prefix to e.g. `APP` or remove it completely and pass empty string `''`

```php
use OriNette\DI\Boot\Environment;

$configurator->addStaticParameters(Environment::loadEnvParameters('APP', ':'));
```

This method uses `$_SERVER` instead of `getenv()` and so is safe to use under any conditions and is compatible with
`.env` file libraries like [symfony/dotenv](https://github.com/symfony/dotenv).

### Testing mode

Generated container is cached on disk and does not reload unless one of dependencies changed and debug mode is enabled.
While this makes sense during application runtime and development, it creates code coverage issues in automated tests.
Compile-time code like compiler extensions is executed only once and second time tests are run, code is incorrectly
reported as uncovered. This issue can be solved by always reloading container:

```php
$configurator->setForceReloadContainer();
```

### Import services

In rare cases it may be useful to import a service into DI container via configurator. To do so, register service
with `imported: true`.

```neon
services:
	serviceName:
		type: ExampleService
		imported: true
```

In the bootstrap add the actual service instance.

```php
$configurator->addServices([
	'serviceName' => new ExampleService(),
]);
```

### Compilation

In rare cases it may be useful to do something only when new `Container` is compiled. In such case, use the `onCompile`
event.

```php
use Nette\DI\Compiler;

$configurator->onCompile[] = function (Compiler $compiler): void {
	// Do anything you want
};
```

### Cache warmup

It is useful to create compiled `Container` on application deploy to speed up first requests.

```php
$configurator->loadContainer();
```

It's even possible to create multiple containers at the same time

```php
$configurator->addStaticParameters([
	'consoleMode' => true,
	'debugMode' => true,
]);

$configurator->loadContainer();

$configurator->addStaticParameters([
	'consoleMode' => false,
	'debugMode' => false,
]);

$configurator->loadContainer();

// etc.
```

## Definitions loader

Extensions can accept services in any format which is allowed by `services` section in the configuration and also accept
references via `@serviceName` to services from `services`. To achieve this follow example below:

These are all valid ways how to write a service:

```neon
extensions:
	example: ExampleExtension

services:
	referenced: ExampleService
	referencedByType: AnotherExampleService

example:
	services:
		string: ExampleService
		statement: ExampleService()
		reference: @referenced
		referenceByType: @AnotherExampleService
		array:
			factory: ExampleService
```

Services loaded via `DefinitionsLoader` are *not autowired* by default because they are extension-specific. Autowiring
of services referenced via `@serviceName` or with `autowired` explicitly set is not changed.

```neon
example:
	services:
		arrayWithAutowiringSet:
			factory: ExampleService
			autowired: true
```

Integration of `DefinitionsLoader` which would load these definitions may look like this:

```php
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use OriNette\DI\Definitions\DefinitionsLoader;

final class ExampleExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'services' => Expect::arrayOf(
				DefinitionsLoader::schema(),
			),
		]);
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		$loader = new DefinitionsLoader($this->compiler);

		$config = $this->config;
		foreach ($config->services as $serviceName => $serviceConfig) {
			$definition = $loader->loadDefinitionFromConfig(
				$serviceConfig,
				// service name (in case of @referenced is defined an alias)
				$this->prefix('definition.' . $serviceName)
			);

			// Do anything you want with the definition
			//  - returns Reference if @referenced service was not loaded yet or instance of Definition otherwise
		}
	}

}
```

## Service manager

`ServiceManager` is a base class useful for lazy loading of multiple services of the same type.

Internally it uses array of service names obtainable by keys from DI container.

Note: Same as [nette/di](https://github.com/nette/di/) factories and accessors, this is not a service locator pattern,
because obtained services are fully configured from outside.

Example implementation which returns all services and validates they exist and are of certain type may look like this:

```php
use OriNette\DI\Services\ServiceManager;

final class ExampleManager extends ServiceManager
{

	/** @var array<Example>|null */
	private ?array $examples = null;

	/**
	 * @return array<Example>
	 */
	public function getAll(): array
	{
		if ($this->examples !== null) {
			return $this->examples;
		}

		$loaders = [];
		foreach ($this->getKeys() as $key) {
			$loaders[$key] = $this->getTypedServiceOrThrow($key, Example::class);
		}

		return $this->examples = $loaders;
	}

```

Or get services one by one, with possible nulls:

```php
use OriNette\DI\Services\ServiceManager;

final class ExampleManager extends ServiceManager
{

	/** @var array<int|string, Example|null> */
	private array $examples = [];

	/**
	 * @param int|string $key
	 */
	public function get($key): ?Example
	{
		if (array_key_exists($key, $this->examples)) {
			return $this->examples[$key];
		}

		return $this->examples[$key] = $this->getTypedService($key, Example::class);
	}

}
```

Service manager may be registered in config like this:

```neon
services:
	- factory: ExampleManager
	  arguments:
		serviceMap:
			key: service.name
			anotherKey: another.service.name
```

You may combine various *protected* methods of `ServiceManager` to achieve various goals:

- `hasService(int|string $key): bool`
- `getService(int|string $key): ?object`
- `getTypedService(int|string $key, class-string<T> $type): ?T`
- `getTypedServiceOrThrow(int|string $key, class-string<T> $type): T`
- `getServiceName(int|string $key): string`
- `getKeys(): array<int, int|string>`
- `throwMissingService(int|string $key, class-string $expectedType): never`
- `throwInvalidServiceType(int|string $key, class-string $expectedType, object $service): never`
