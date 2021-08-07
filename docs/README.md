# Nette DI

Configure your Orisai CMF/Nette application

## Content

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
	- [Import services](#import-services)
	- [Compilation](#compilation)
	- [Cache warmup](#cache-warmup)
- [Definitions loader](#definitions-loader)

## Configurator

An alternative for [nette/bootstrap](https://github.com/nette/bootstrap)

- none of the extensions is loaded by default
- debug mode is disabled unless you enable it

To use it, create bootstrap class where you preconfigure your application

```php
namespace App\Boot;

use OriNette\DI\Boot\Environment;
use OriNette\DI\Boot\ManualConfigurator;

final class Bootstrap
{

	public static function boot(): ManualConfigurator
	{
		$rootDir = dirname(__DIR__, 2);
		$configurator = new ManualConfigurator($rootDir);

		$configurator->addStaticParameters(Environment::loadEnvParameters());

		$configurator->setDebugMode(
			Environment::isEnvDebugMode() ||
			Environment::isLocalhost() ||
			Environment::hasCookie(self::getDebugCookieValues()),
		);
		$configurator->enableDebugger();

		$configurator->addConfig(__DIR__ . '/../config/common.neon');
		$configurator->addConfig(__DIR__ . '/../config/server/local.neon');

		return $configurator;
	}

	/**
	 * @return array
 	 */
	private static function getDebugCookieValues(): array
	{
		return [];
	}

}
```

In application entrypoint (`index.php`) - get the configurator, create a container, get the application and run it.

```php
use App\Boot\Bootstrap;
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
$configurator->addConfig(__DIR__ . '/../config/common.neon');
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

#### With env variable

Useful for enabling debug mode in console

Set env variable to `true` or `1`

`ORISAI_DEBUG = true`

Check env variable in bootstrap

```php
use OriNette\DI\Boot\Environment;

$configurator->setDebugMode(Environment::isEnvDebugMode());
```

We can also change the variable name to something else

```php
Environment::isEnvDebugMode('VARIABLE_NAME');
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

*Be paranoid and always generate long cookie values like 100 characters long*

We can also change the cookie name to something else

```php
Environment::hasCookie($cookieValues, 'cookie-name');
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

example:
	services:
		string: ExampleService
		statement: ExampleService()
		reference: @referenced
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
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use OriNette\DI\Definitions\DefinitionsLoader;

final class ExampleExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'services' => Expect::arrayOf(
				Expect::anyOf(Expect::string(), Expect::array(), Expect::type(Statement::class)),
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
