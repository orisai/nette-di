<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Boot\Extensions;

use OriNette\DI\Boot\ManualConfigurator;
use PHPUnit\Framework\TestCase;
use function dirname;
use function mkdir;
use const PHP_VERSION_ID;

/**
 * @runTestsInSeparateProcesses
 */
final class ConstantsExtensionTest extends TestCase
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
		$configurator->addConfig(__DIR__ . '/ConstantsExtension.neon');

		$configurator->createContainer(); // Initialized constants

		self::assertSame('hello', a);
		self::assertSame('WORLD', A);
		self::assertSame(123, b);
		self::assertSame(1.23, c);
		self::assertTrue(d);
		self::assertFalse(e);
		self::assertNull(f);
		self::assertSame([], g);
	}

}
