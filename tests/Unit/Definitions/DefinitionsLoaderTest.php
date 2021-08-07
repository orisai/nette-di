<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Definitions;

use Generator;
use Nette\DI\Compiler;
use OriNette\DI\Boot\ManualConfigurator;
use Orisai\Exceptions\Logic\InvalidArgument;
use PHPUnit\Framework\TestCase;
use Tests\OriNette\DI\Doubles\DefinitionsList;
use Tests\OriNette\DI\Doubles\DefinitionsLoadingExtension;
use Tests\OriNette\DI\Doubles\TestService;
use function assert;
use function dirname;

final class DefinitionsLoaderTest extends TestCase
{

	/**
	 * @dataProvider resolvingProvider
	 */
	public function testResolving(bool $loadLater): void
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

		// /////
		// All declared services exist
		// /////
		$referencedService = $container->getService('referenced');
		self::assertInstanceOf(TestService::class, $referencedService);

		$referencedServiceAlias = $container->getService('loader.definition.reference');
		self::assertSame($referencedService, $referencedServiceAlias);

		$stringService = $container->getService('loader.definition.string');
		self::assertInstanceOf(TestService::class, $stringService);

		$statementService = $container->getService('loader.definition.statement');
		self::assertInstanceOf(TestService::class, $statementService);

		$arrayService = $container->getService('loader.definition.array');
		self::assertInstanceOf(TestService::class, $arrayService);

		$arrayWithAutowiringService = $container->getService('loader.definition.arrayWithAutowiringSet');
		self::assertInstanceOf(TestService::class, $arrayWithAutowiringService);

		self::assertCount(5, $container->findByType(TestService::class));
		// Only 'referenced' from services and 'arrayWithAutowiringSet' from extension are autowired
		self::assertCount(2, $container->findAutowired(TestService::class));

		// /////
		// Double escaped message is not handled as a parameter
		// /////
		assert($referencedService instanceof TestService);
		assert($arrayService instanceof TestService);

		self::assertSame([
			['%message%'],
		], $referencedService->getParams());

		self::assertSame([
			['%message%'],
		], $arrayService->getParams());

		// /////
		// References and definitions are properly resolved and have correct values in compile time
		// /////
		$referencedDefinition = $loadLater
			? [
				'type' => 'definition',
				'name' => 'referenced',
				'serviceType' => TestService::class,
				'autowired' => false,
				'service' => $referencedService,
			]
			: [
				'type' => 'reference',
				'value' => 'referenced',
				'isName' => true,
				'isType' => false,
				'isSelf' => false,
				'service' => $referencedService,
			];

		$list = $container->getByType(DefinitionsList::class);
		self::assertSame(
			[
				'loader.definition.string' => [
					'type' => 'definition',
					'name' => 'loader.definition.string',
					'serviceType' => null,
					'autowired' => false,
					'service' => $stringService,
				],
				'loader.definition.statement' => [
					'type' => 'definition',
					'name' => 'loader.definition.statement',
					'serviceType' => null,
					'autowired' => false,
					'service' => $statementService,
				],
				'loader.definition.reference' => $referencedDefinition,
				'loader.definition.array' => [
					'type' => 'definition',
					'name' => 'loader.definition.array',
					'serviceType' => null,
					'autowired' => false,
					'service' => $arrayService,
				],
				'loader.definition.arrayWithAutowiringSet' => [
					'type' => 'definition',
					'name' => 'loader.definition.arrayWithAutowiringSet',
					'serviceType' => null,
					'autowired' => false,
					'service' => $arrayWithAutowiringService,
				],
			],
			$list->getList(),
		);
	}

	/**
	 * @return Generator<array<bool>>
	 */
	public function resolvingProvider(): Generator
	{
		yield 'loadInLoadConfiguration' => [false];
		yield 'loadInBeforeCompile' => [true];
	}

	public function testSelfReference(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addStaticParameters([
			'__unique' => __METHOD__,
		]);
		$configurator->addConfig(__DIR__ . '/definitions.self.neon');

		$configurator->onCompile[] = static function (Compiler $compiler): void {
			$compiler->addExtension('loader', new DefinitionsLoadingExtension(false));
		};

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage('Referencing @self in unsupported context of loader.definition.error.');

		$configurator->createContainer();
	}

}
