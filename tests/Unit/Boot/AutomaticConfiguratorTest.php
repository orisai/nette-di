<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Boot;

use OriNette\DI\Boot\AutomaticConfigurator;
use Orisai\Installer\Loader\DefaultLoader;
use PHPUnit\Framework\TestCase;
use function dirname;
use function mkdir;
use const PHP_VERSION_ID;

final class AutomaticConfiguratorTest extends TestCase
{

	private string $rootDir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->rootDir = dirname(__DIR__, 3);
		if (PHP_VERSION_ID < 8_01_00) {
			@mkdir("$this->rootDir/var/build");
		}
	}

	public function testModules(): void
	{
		$configurator = new AutomaticConfigurator($this->rootDir, new DefaultLoader());
		$configurator->setForceReloadContainer();

		$container = $configurator->createContainer();

		$parameters = $container->getParameters();

		self::assertArrayHasKey('modules', $parameters);
		self::assertSame(
			$parameters['modules'],
			[
				'orisai_nette-di' => [
					'dir' => $this->rootDir,
				],
				'root' => [
					'dir' => $this->rootDir,
				],
			],
		);
	}

}
