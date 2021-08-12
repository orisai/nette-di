<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Boot;

use OriNette\DI\Boot\ManualConfigurator;
use PHPUnit\Framework\TestCase;
use Tests\OriNette\DI\Doubles\JsonAdapter;
use Tests\OriNette\DI\Doubles\TestService;
use function dirname;

final class ManualConfiguratorTest extends TestCase
{

	private string $rootDir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->rootDir = dirname(__DIR__, 3);
	}

	public function testConfigFilesContent(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setDebugMode(true);

		$configurator->addConfig(__DIR__ . '/priority-service1.neon');
		$configurator->addConfig(__DIR__ . '/priority-service2.neon');

		$container = $configurator->createContainer();
		self::assertInstanceOf(TestService::class, $container->getByType(TestService::class));
	}

	public function testConfigAdapter(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setDebugMode(true);

		$configurator->addConfig(__DIR__ . '/json-config.json');
		$configurator->addConfigAdapter('json', new JsonAdapter());

		$container = $configurator->createContainer();
		self::assertInstanceOf(TestService::class, $container->getByType(TestService::class));
	}

}
