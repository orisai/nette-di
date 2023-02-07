<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Boot\Extensions;

use OriNette\DI\Boot\ManualConfigurator;
use PHPUnit\Framework\TestCase;
use function dirname;
use function ini_get;
use function ini_set;
use function mkdir;
use const PHP_VERSION_ID;

final class PhpExtensionTest extends TestCase
{

	private string $rootDir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->rootDir = dirname(__DIR__, 4);
		if (PHP_VERSION_ID < 8_01_00) {
			@mkdir("$this->rootDir/var/build");
		}
	}

	public function test(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/PhpExtension.neon');

		ini_set('date.timezone', 'Europe/Prague');
		$configurator->createContainer(); // Call ini_set()

		self::assertSame('Europe/Rome', ini_get('date.timezone'));
	}

}
