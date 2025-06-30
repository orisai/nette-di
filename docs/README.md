# Nette DI

Configure your Nette application

## Content

- [Setup](#setup)
- [Configurator](#configurator)
	- [Config files](#config-files)
	- [Debug mode](#debug-mode)
		- [At localhost](#at-localhost)
		- [In console](#in-console)
		- [With env variable](#with-env-variable)
		- [With cookie - manually configured](#with-cookie---manually-configured)
		- [With cookie - switched at runtime](#with-cookie---switched-at-runtime)
	- [Parameters](#parameters)
		- [Predefined parameters](#predefined-parameters)
		- [Static parameters](#static-parameters)
		- [Dynamic parameters](#dynamic-parameters)
		- [Load parameters from env variables](#load-parameters-from-env-variables)
	- [Testing mode](#testing-mode)
	- [Import services](#import-services)
	- [Compilation](#compilation)
	- [Cache warm-up](#cache-warmup)
	- [Differences from nette/bootstrap](#differences-from-nettebootstrap)
- [DI extensions](#di-extensions)
	- [Constants extension](#constants-extension)
	- [PHP extension](#php-extension)
- [Definitions and services](#definitions-and-services)
	- [Definitions loader](#definitions-loader)
	- [Service manager](#service-manager)

## Setup

Install with [Composer](https://getcomposer.org)

```sh
composer require orisai/nette-di
```

## Configurator

Configurator builds DI container and runs the whole application.

It is an alternative to [nette/bootstrap](https://github.com/nette/bootstrap).

- Extensions are not loaded by default and have to be explicitly registered.
- Debug mode is not auto-detected and has to be explicitly enabled.
- Read more about differences and reasons behind them [here](#differences-from-nettebootstrap).

Create a bootstrap class where you pre‑configure your application:

> [!NOTE]
> This is just an example, [enable debug mode](#debug-mode) and [add config files](#config-files) the way you need.

```php
namespace App;

use OriNette\DI\Boot\Environment;
use OriNette\DI\Boot\ManualConfigurator;
use function dirname;

final class Bootstrap
{

	public static function boot(): ManualConfigurator
	{
		$rootDir = dirname(__DIR__);
		$configurator = new ManualConfigurator($rootDir);

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
	 * @return list<string>
	 */
	private static function getDebugCookieValues(): array
	{
		return [];
	}

}
```

In the application entry point (`index.php`) – get the configurator, create a container, get the application and run it:

```php
use App\Bootstrap;
use Nette\Application\Application;

require __DIR__ . '/../vendor/autoload.php';

Bootstrap::boot()
	->createContainer()
	->getByType(Application::class)
	->run();
```

### Config files

Add configuration files:

```php
$configurator->addConfig(__DIR__ . '/../config/local.neon');
```

Configurator has built-in support for `.neon` and `.php` files. For other formats, create own implementation of
`Nette\DI\Config\Adapter`:

```php
$configurator->addConfigAdapter('json', new JsonAdapter());
```

### Debug mode

When debug mode is on, the container regenerates whenever any configuration file or service changes.

Enable or disable debug mode:

```php
$configurator->setDebugMode(
	Environment::isEnvDebug()
	|| Environment::isLocalhost()
);
```

And enable [Tracy](https://github.com/nette/tracy) debugger (if installed):

```php
$configurator->enableDebugger();
```

#### At localhost

```php
use OriNette\DI\Boot\Environment;

$configurator->setDebugMode(Environment::isLocalhost());
```

> [!TIP]
> This approach does not work when running app behind a proxy, because it would be unsafe to support it for apps without
> proxy. For proxied apps prefer the [env variable](#with-env-variable) approach.

#### In console

```php
use OriNette\DI\Boot\Environment;

$configurator->setDebugMode(Environment::isConsole());
```

> [!TIP]
> Prefer the [env variable](#with-env-variable) approach for local console work; otherwise production CLI will
> also run in debug mode.

#### With env variable

Define env variable `ORISAI_DEBUG` with a truthy value (`1` or `true`).

```sh
# Temporary – current shell session only
export ORISAI_DEBUG=1

# Persistent – Bash
echo 'export ORISAI_DEBUG=1' >> ~/.bashrc
source ~/.bashrc # load without opening a new terminal

# Persistent – Z shell
echo 'export ORISAI_DEBUG=1' >> ~/.zshrc
source ~/.zshrc

# Persistent – system‑wide (Debian‑based)
echo 'ORISAI_DEBUG=1' | sudo tee -a /etc/environment
```

Check it in bootstrap:

```php
use OriNette\DI\Boot\Environment;

$configurator->setDebugMode(Environment::isEnvDebug());
```

The variable name can be changed:

```php
Environment::isEnvDebug('APP_DEBUG');
```

#### With cookie - manually configured

> [!CAUTION]
> It is critical to use long and cryptographically secure values. With debug mode enabled, an attacker can retrieve all
> app credentials and more.

Generate a secure cookie value:

```php
echo bin2hex(random_bytes(128));
```

Set a debug cookie in your browser:

```
orisai-debug = really_long_and_secure_cookie_value
```

Check the cookie value in bootstrap:

```php
$configurator->setDebugMode(Environment::hasCookie([
	'really_long_and_secure_cookie_value',
	'another_really_long_and_secure_cookie_value',
]));
```

You can also change the cookie name:

```php
Environment::hasCookie($cookieValues, 'cookie-name');
```

List the cookie values via an env variable:

```php
use OriNette\DI\Boot\CookieGetter;

Environment::hasCookie(CookieGetter::fromEnv());
```

#### With cookie - switched at runtime

Enable debug mode with a click inside your administration UI:

```php
use OriNette\DI\Boot\Environment;
use OriNette\DI\Boot\FileDebugCookieStorage;

$cookieStorage = new FileDebugCookieStorage(__DIR__ . '/debug-cookie-values.json');
$configurator->addServices([
	'orisai.di.cookie.storage' => $cookieStorage,
]);

$configurator->setDebugMode(
	Environment::isCookieDebug($cookieStorage),
);
```

Register the debug switcher and storage as services:

```neon
services:
	orisai.di.cookie.storage:
		type: OriNette\DI\Boot\DebugCookieStorage
		imported: true

	orisai.di.cookie.debugSwitcher: OriNette\DI\Bridge\NetteHttp\CookieDebugSwitcher
```

Switch debug mode in a presenter:

```php
use Nette\Application\UI\Presenter;
use OriNette\DI\Bridge\NetteHttp\CookieDebugSwitcher;

final class DevPresenter extends Presenter
{

	private CookieDebugSwitcher $cookieDebugSwitcher;

	public function __construct(CookieDebugSwitcher $cookieDebugSwitcher)
	{
		parent::__construct();
		$this->cookieDebugSwitcher = $cookieDebugSwitcher;
	}

	public function handleSwitchDebug(): void
	{
		if (/* TODO – check permission */) {
			$this->error();
		}

		if ($this->cookieDebugSwitcher->isDebug()) {
			$this->cookieDebugSwitcher->stopDebug();
		} else {
			$this->cookieDebugSwitcher->startDebug();
		}

		$this->redirect('this');
	}

	public function renderDefault(): void
	{
		$this->template->isCookieDebug = $this->cookieDebugSwitcher->isDebug();
	}

}
```

Create links to the switcher:

```latte
{* TODO – check permission *}
<a n:href="switchDebug!" type="button">
	{if $isCookieDebug}
	Stop debug
	{else}
	Start debug
	{/if}
</a>
```

### Parameters

Parameters are values used for configuring services and are available in neon via `%parameterName%` syntax and in
compiler extensions.

#### Predefined parameters

|          Parameter | Description                                                              | Example / Default                                                        |
|-------------------:|:-------------------------------------------------------------------------|:-------------------------------------------------------------------------|
|        `%rootDir%` | Base path to your app                                                    | `/path/to/project`                                                       |
|         `%appDir%` | Source‑code path                                                         | `%rootDir%/src`                                                          |
|        `%dataDir%` | Uploaded data path                                                       | `%rootDir%/data`                                                         |
|         `%logDir%` | Log files path                                                           | `%rootDir%/var/log`                                                      |
|       `%buildDir%` | Permanently stored cache path                                            | `%rootDir%/var/build`                                                    |
|        `%tempDir%` | Temporarily stored cache path                                            | `%rootDir%/var/tmp`                                                      |
|      `%vendorDir%` | Composer libraries path                                                  | `%rootDir%/vendor`                                                       |
|         `%wwwDir%` | Public directory (web‑server‑accessible)                                 | `%rootDir%/public`                                                       |
|        `%baseUrl%` | Base URL of your app (needs [nette/http](https://github.com/nette/http)) | e.g. `https://example.com`                                               |
|      `%debugMode%` | Is the application in **debug** mode?                                    | `false`                                                                  |
| `%productionMode%` | Opposite of debug mode                                                   | `true`                                                                   |
|    `%consoleMode%` | Is the application running in CLI?                                       | `PHP_SAPI === 'cli'`                                                     |
|      `%container%` | Info about the DI container                                              | `array{className: string, compiledAt: string, compiledAtTimestamp: int}` |

#### Static parameters

Static parameters do not change at all or have just a few variations. New container is generated every time parameter is
added, removed or when its value changes.

```php
$configurator->addStaticParameters([
	'parameter' => 'value',
]);
```

#### Dynamic parameters

A dynamic parameter’s value can change on every request; new container is generated only when parameter is added or
removed.

```php
$configurator->addDynamicParameters([
	'parameter' => 'value',
]);
```

> [!WARNING]
> Unless the value is dynamic, prefer static parameters. Dynamic parameters are not available during compile-time and
> may cause degraded performance.

#### Load parameters from env variables

Transform env variables into parameters:

```php
use OriNette\DI\Boot\Environment;

$configurator->addStaticParameters(Environment::loadEnvParameters());
```

Env‑vars match the pattern `PREFIX{delimiter}{NAME‑1}{delimiter}{NAME‑N}`. The default prefix is **ORISAI** and the delimiter is `__`.

```dotenv
ORISAI__PARAMETER=1
ORISAI__SINGLE_UNDERSCORE=2
ORISAI__UPPER__lower__MiXeD=3
ORISAI__UPPER__another__parameter=4
```

```neon
parameters:
	parameter: 1
	single_underscore: 2
	upper:
		lower:
			mixed: 3
		another:
			parameter: 4
```

Delimiter and prefix can be changed:

```php
$configurator->addStaticParameters(Environment::loadEnvParameters('APP', ':'));
// APP:PARAMETER=1
```

> [!NOTE]
> Implementation is compatible with various runtimes and libraries such
> as [symfony/dotenv](https://github.com/symfony/dotenv)

### Testing mode

The compiled container is cached on disk. During test runs this can break code‑coverage because compile‑time code like
compiler extensions runs only once. Force a reload:

```php
$configurator->setForceReloadContainer();
```

Need to test a failure inside `initialize()`? Create the container without initializing and handle it yourself:

```php
$container = $configurator->createContainer(false);
// …
$container->initialize();
```

### Import services

Import a runtime‑created service into the DI container by marking it as `imported: true`:

```neon
services:
	serviceName:
		type: ExampleService
		imported: true
```

And provide the instance in bootstrap:

```php
$configurator->addServices([
	'serviceName' => new ExampleService(),
]);
```

### Compilation

Run code only when the container is freshly compiled:

```php
use Nette\DI\Compiler;

$configurator->onCompile[] = function (Compiler $compiler): void {
	// custom compile‑time logic
};
```

### Cache warmup

Warm up the compiled container during deploy to speed up the first requests:

```php
$configurator->loadContainer();
```

> [!IMPORTANT]
> `loadContainer()` should be used instead of `createContainer()`. Otherwise, container would be instantiated and may
> cause undesired side effects.

Generate multiple variants if needed:

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
```

### Differences from nette/bootstrap

- Paths are based on root path instead of being automatically detected. This leads to lighter boot code and prevents
  issues with `%wwwDir%` being wrong in console scripts and `%appDir%` being wrong when bootstrap is located elsewhere.
	- On the other hand, `%vendorDir%` is set to `%rootDir%/vendor` instead of being detected based on Composer
	  settings.
- Default paths for `%appDir%`, `%logDir%`, `%tempDir%` and `%wwwDir%` match structure commonly used in Linux instead of
  structure of nette/web-project.
- `%buildDir` was introduced to divide permanent, generated files (compiled DI container and Latte templates) from
  cache.
- [Debug mode](#debug-mode) is not auto-detected and has to be explicitly enabled. Various new method of enabling it are
  provided.
- [Testing mode](#testing-mode) can be enabled for easier compile-time code coverage in tests.
- Extensions are not loaded by default and have to be explicitly registered. This leads to lighter boot code and
  simplifies testing of packages with optional dependencies.

These are all the extensions registered by [nette/bootstrap](https://github.com/nette/bootstrap). Add those that you
need to your configuration file.

```neon
extensions:
	application: Nette\Bridges\ApplicationDI\ApplicationExtension(%debugMode%, %appDir%, %tempDir%/nette.application)
	assets: Nette\Bridges\AssetsDI\DIExtension(%baseUrl%, %wwwDir%, %debugMode%)
	cache: Nette\Bridges\CacheDI\CacheExtension(%tempDir%/nette.caching)
	constants: OriNette\DI\Boot\Extensions\ConstantsExtension()
	database: Nette\Bridges\DatabaseDI\DatabaseExtension(%debugMode%)
	decorator: Nette\DI\Extensions\DecoratorExtension()
	di: Nette\DI\Extensions\DIExtension(%debugMode%)
	extensions: Nette\DI\Extensions\ExtensionsExtension()
	forms: Nette\Bridges\FormsDI\FormsExtension()
	http: Nette\Bridges\HttpDI\HttpExtension(%consoleMode%)
	inject: Nette\DI\Extensions\InjectExtension()
	latte: Nette\Bridges\ApplicationDI\LatteExtension(%buildDir%/latte, %debugMode%)
	mail: Nette\Bridges\MailDI\MailExtension()
	php: OriNette\DI\Boot\Extensions\PhpExtension()
	routing: Nette\Bridges\ApplicationDI\RoutingExtension(%debugMode%)
	search: Nette\DI\Extensions\SearchExtension(%tempDir%/nette.search)
	security: Nette\Bridges\SecurityDI\SecurityExtension(%debugMode%)
	session: Nette\Bridges\HttpDI\SessionExtension(%debugMode%, %consoleMode%)
	tracy: Tracy\Bridges\Nette\TracyExtension(%debugMode%, %consoleMode%)
```

## DI extensions

### Constants extension

Define PHP constants via `define()` when the DI container is instantiated:

```neon
extensions:
	constants: OriNette\DI\Boot\Extensions\ConstantsExtension

constants:
	constantName: constantValue
```

### PHP extension

Set [`php.ini` directives](https://www.php.net/manual/en/ini.list.php) via
[`ini_set()`](https://www.php.net/manual/en/function.ini-set) when the container is instantiated:

```neon
extensions:
	php: OriNette\DI\Boot\Extensions\PhpExtension

php:
	date.timezone: UTC
```

## Definitions and services

### Definitions loader

With definitions loader, extensions can accept services in any syntax supported by the `services` section and may
reference existing services via `@serviceName`.

```neon
extensions:
	example: ExampleExtension

services:
	referenced.key: ExampleService
	referenced.type: AnotherExampleService

example:
	services:
		string: ExampleService
		statement: ExampleService()
		reference: @referenced.key
		referenceByType: @AnotherExampleService
		array:
			factory: ExampleService
```

Services loaded through `DefinitionsLoader` are **not autowired** by default because they are extension‑specific. You
can still opt‑in to autowiring:

```neon
example:
	services:
		arrayWithAutowiringSet:
			factory: ExampleService
			autowired: true
```

A minimal integration looks like this:

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
			'services' => Expect::arrayOf(DefinitionsLoader::schema()),
		]);
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		$loader = new DefinitionsLoader($this->compiler);

		$config = $this->config;
		foreach ($config->services as $serviceName => $serviceConfig) {
			// Returns Reference for @referenced services that were not resolved yet and Definitions for all others
			$definition = $loader->loadDefinitionFromConfig(
				$serviceConfig,
				// service name (in case of @reference to an existing service, alias is added instead)
				$this->prefix('definition.' . $serviceName)
			);
		}
	}

}
```

### Service manager

`ServiceManager` helps lazy‑load a set of related services. Internally it uses a map of service names provided via DI.

> [!NOTE]
> Like Nette factories and accessors, this is **not** the service‑locator anti‑pattern because services are
> fully configured from outside.

Return all services and validate their types:

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

		$instances = [];
		foreach ($this->getKeys() as $key) {
			$instances[$key] = $this->getTypedServiceOrThrow($key, Example::class);
		}

		return $this->examples = $instances;
	}

}
```

Fetch services one by one and allow `null`:

```php
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

Register the manager in config:

```neon
services:
	-
		factory: ExampleManager
		arguments:
		serviceMap:
			key: service.name
			anotherKey: another.service.name
```

Useful (protected) helpers inside `ServiceManager`:

- `hasService(int|string $key): bool`
- `getService(int|string $key): ?object`
- `getTypedService(int|string $key, class-string<T> $type): ?T`
- `getTypedServiceOrThrow(int|string $key, class-string<T> $type): T`
- `getServiceName(int|string $key): string`
- `getKeys(): array<int, int|string>`
- `throwMissingService(int|string $key, class-string $expectedType): never`
- `throwInvalidServiceType(int|string $key, class-string $expectedType, object $service): never`
