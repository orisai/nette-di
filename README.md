<h1 align="center">
	<img src="https://github.com/orisai/.github/blob/main/images/repo_title.png?raw=true" alt="Orisai"/>
	<br/>
	Nette DI
</h1>

<p align="center">
    Configure your Nette application
</p>

<p align="center">
	📄 Check out our <a href="docs/README.md">documentation</a>.
</p>

<p align="center">
	💸 If you like Orisai, please <a href="https://orisai.dev/sponsor">make a donation</a>. Thank you!
</p>

<p align="center">
	<a href="https://github.com/orisai/nette-di/actions?query=workflow%3ACI">
		<img src="https://github.com/orisai/nette-di/workflows/CI/badge.svg">
	</a>
	<a href="https://coveralls.io/r/orisai/nette-di">
		<img src="https://badgen.net/coveralls/c/github/orisai/nette-di/v1.x?cache=300">
	</a>
	<a href="https://dashboard.stryker-mutator.io/reports/github.com/orisai/nette-di/v1.x">
		<img src="https://badge.stryker-mutator.io/github.com/orisai/nette-di/v1.x">
	</a>
	<a href="https://packagist.org/packages/orisai/nette-di">
		<img src="https://badgen.net/packagist/dt/orisai/nette-di?cache=3600">
	</a>
	<a href="https://packagist.org/packages/orisai/nette-di">
		<img src="https://badgen.net/packagist/v/orisai/nette-di?cache=3600">
	</a>
	<a href="https://choosealicense.com/licenses/mpl-2.0/">
		<img src="https://badgen.net/badge/license/MPL-2.0/blue?cache=3600">
	</a>
<p>

##

```php
namespace App;

use OriNette\DI\Boot\Environment;
use OriNette\DI\Boot\ManualConfigurator;

final class Bootstrap
{

	public static function boot(): ManualConfigurator
	{
		$configurator = new ManualConfigurator(dirname(__DIR__));

		$configurator->setDebugMode(
			Environment::isEnvDebug()
			|| Environment::isLocalhost()
		);
		$configurator->enableDebugger();

		$configurator->addConfig(__DIR__ . '/wiring.neon');
		$configurator->addConfig(__DIR__ . '/../config/local.neon');

		return $configurator;
	}

}
```

... and [more](docs/README.md).
