<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Integration\Definitions;

use Generator;
use Nette\DI\Compiler;
use OriNette\DI\Boot\ManualConfigurator;
use PHPUnit\Framework\TestCase;
use Tests\OriNette\DI\Doubles\DefinitionsLoadingExtension;
use Tests\OriNette\DI\Doubles\TestService;
use function assert;
use function dirname;

final class DefinitionsLoaderTest extends TestCase
{

	/**
	 * @dataProvider provideTestData
	 */
	public function test(bool $loadLater): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addStaticParameters([
			'__unique' => __METHOD__,
			'load_later' => $loadLater,
		]);
		$configurator->addConfig(__DIR__ . '/definitions.neon');

		$configurator->onCompile[] = static function (Compiler $compiler) use ($loadLater): void {
			$compiler->addExtension('loader', new DefinitionsLoadingExtension($loadLater));
		};

		$container = $configurator->createContainer();

		self::assertInstanceOf(TestService::class, $container->getService('referenced'));
		self::assertInstanceOf(TestService::class, $container->getService('loader.definition.string'));
		self::assertInstanceOf(TestService::class, $container->getService('loader.definition.statement'));
		self::assertInstanceOf(TestService::class, $container->getService('loader.definition.array'));
		self::assertInstanceOf(TestService::class, $container->getService('loader.definition.arrayWithAutowiringSet'));
		self::assertFalse($container->hasService('loader.definition.reference'));
		self::assertCount(5, $container->findByType(TestService::class));
		// 'referenced' from services and 'arrayWithAutowiringSet' from extension
		self::assertCount(2, $container->findAutowired(TestService::class));

		$referencedService = $container->getService('referenced');
		assert($referencedService instanceof TestService);
		$arrayService = $container->getService('loader.definition.array');
		assert($arrayService instanceof TestService);

		self::assertSame([
			['%message%'],
		], $referencedService->getParams());

		self::assertSame([
			['%message%'],
		], $arrayService->getParams());
	}

	/**
	 * @return Generator<array<bool>>
	 */
	public function provideTestData(): Generator
	{
		yield 'loadInLoadConfiguration' => [false];
		yield 'loadInBeforeCompile' => [true];
	}

}
