<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Boot;

use Generator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\Config\Adapter;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use OriNette\DI\Boot\ManualConfigurator;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Utils\Dependencies\DependenciesTester;
use Orisai\Utils\Dependencies\Exception\PackageRequired;
use Orisai\VFS\VFS;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\OriNette\DI\Doubles\ParametersAddingExtension;
use Tests\OriNette\DI\Doubles\TestingConfigurator;
use Tests\OriNette\DI\Doubles\TestService;
use Tracy\Debugger;
use function class_exists;
use function dirname;
use function file_put_contents;
use function is_subclass_of;
use function mkdir;
use const PHP_SAPI;
use const PHP_VERSION_ID;

final class BaseConfiguratorTest extends TestCase
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

	public function testCreateContainer(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$class = $configurator->loadContainer();

		self::assertTrue(class_exists($class));
		self::assertTrue(is_subclass_of($class, Container::class));

		$configurator->createContainer();
	}

	public function testForceReloadTwice(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters([
			'__unique' => __METHOD__,
		]);

		$class = $configurator->loadContainer();
		self::assertFileExists("$this->rootDir/var/build/orisai.di.configurator/$class.php");
		self::assertSame($class, $configurator->loadContainer());
		self::assertFileExists("$this->rootDir/var/build/orisai.di.configurator/$class.php");
	}

	public function testDebugContainer(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();

		$container1 = $configurator->loadContainer();
		$container2 = $configurator->loadContainer();
		self::assertSame($container1, $container2);

		$configurator->setDebugMode(true);
		$container3 = $configurator->loadContainer();
		self::assertNotSame($container1, $container3);
	}

	public function testParametersMatch(): void
	{
		$rootDir = $this->rootDir;
		$configurator = new TestingConfigurator($rootDir);
		$configurator->setForceReloadContainer();

		self::assertSame(PHP_SAPI === 'cli', $configurator->isConsoleMode());

		self::assertFalse($configurator->isDebugMode());
		$configurator->setDebugMode(true);
		self::assertTrue($configurator->isDebugMode());

		$configurator->addStaticParameters(['test' => 'test', 'consoleMode' => true]);
		$configurator->addDynamicParameters(['dynamic' => 'dynamic']);

		$container = $configurator->createContainer();
		$parameters = $container->getParameters();

		self::assertSame($rootDir, $parameters['rootDir']);
		self::assertSame($rootDir . '/src', $parameters['appDir']);
		self::assertSame($rootDir . '/var/build', $parameters['buildDir']);
		self::assertSame($rootDir . '/data', $parameters['dataDir']);
		self::assertSame($rootDir . '/var/log', $parameters['logDir']);
		self::assertSame($rootDir . '/var/cache', $parameters['tempDir']);
		self::assertSame($rootDir . '/vendor', $parameters['vendorDir']);
		self::assertSame($rootDir . '/public', $parameters['wwwDir']);
		self::assertNull($parameters['baseUrl']);
		self::assertTrue($parameters['debugMode']);
		self::assertFalse($parameters['productionMode']);
		self::assertTrue($parameters['consoleMode']);
		self::assertSame('test', $parameters['test']);
		self::assertSame('dynamic', $parameters['dynamic']);
		self::assertArrayHasKey('container', $parameters);
		self::assertArrayHasKey('compiledAtTimestamp', $parameters['container']);
		self::assertArrayHasKey('compiledAt', $parameters['container']);
		self::assertArrayHasKey('className', $parameters['container']);

		// 12 default + 1 container + 2 from test
		self::assertCount(12 + 1 + 2, $parameters);
		self::assertCount(12, $configurator->getDefaultParameters());
	}

	public function testParametersSpecificContainer(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->setDebugMode(true);

		$basicContainer = $configurator->loadContainer();

		$configurator->addStaticParameters(['static' => 'static1']);
		$static1Container = $configurator->loadContainer();
		self::assertNotSame($basicContainer, $static1Container);

		$configurator->addStaticParameters(['static' => 'static2']);
		$static2Container = $configurator->loadContainer();
		self::assertNotSame($basicContainer, $static2Container);
		self::assertNotSame($static1Container, $static2Container);

		$configurator->addDynamicParameters(['dynamic' => 'dynamic1']);
		$dynamic1Container = $configurator->loadContainer();
		self::assertNotSame($static2Container, $dynamic1Container);

		$configurator->addDynamicParameters(['dynamic' => 'dynamic2']);
		$dynamic2Container = $configurator->loadContainer();
		self::assertSame($dynamic1Container, $dynamic2Container);
	}

	public function testParametersEscaping(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters([
			'param1' => '%test%',
			'param2' => '@test',
		]);

		$container = $configurator->createContainer();
		$parameters = $container->getParameters();

		self::assertSame('%test%', $parameters['param1']);
		self::assertSame('@@test', $parameters['param2']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDebuggerProduction(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->enableDebugger();

		self::assertTrue(Debugger::$strictMode);
		self::assertSame(Debugger::$productionMode, Debugger::PRODUCTION);
		self::assertSame($this->rootDir . '/var/log', Debugger::$logDirectory);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDebuggerDebug(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->setDebugMode(true);
		$configurator->enableDebugger();

		self::assertTrue(Debugger::$strictMode);
		self::assertSame(Debugger::$productionMode, Debugger::DEVELOPMENT);
		self::assertSame($this->rootDir . '/var/log', Debugger::$logDirectory);
	}

	public function testRobotLoader(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$loader = $configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();

		self::assertNotEmpty($loader->getIndexedClasses());
	}

	public function testServices(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addServices([
			'service1' => ($service1 = new TestService()),
			'service2' => ($service2 = new TestService()),
		]);

		$container = $configurator->createContainer();
		self::assertCount(0, $container->findByType(TestService::class));
		self::assertSame($service1, $container->getService('service1'));
		self::assertSame($service2, $container->getService('service2'));

		$configurator->addConfig(__DIR__ . '/imported-services.neon');
		$container = $configurator->createContainer();
		self::assertCount(2, $container->findByType(TestService::class));
		self::assertSame($service1, $container->getService('service1'));
		self::assertSame($service2, $container->getService('service2'));
	}

	public function testExtensions(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->addConfig(__DIR__ . '/extensions.neon');
		$configurator->onCompile[] = static function (Compiler $compiler): void {
			$compiler->addExtension('test3', new ParametersAddingExtension(['test3' => 'test3']));
		};

		$container = $configurator->createContainer();
		$parameters = $container->getParameters();
		self::assertSame('test1', $parameters['test1']);
		self::assertSame('test2', $parameters['test2']);
		self::assertSame('test3', $parameters['test3']);
	}

	public function testConfigFileSpecificContainer(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$containerBase = $configurator->loadContainer();

		$configurator->addConfig(__DIR__ . '/extensions.neon');
		$containerConfig1 = $configurator->loadContainer();
		self::assertNotSame($containerBase, $containerConfig1);

		$configurator->addConfig(__DIR__ . '/priority-service2.neon');
		$containerConfig2 = $configurator->loadContainer();
		self::assertNotSame($containerConfig1, $containerConfig2);
	}

	public function testAutowireExcluded(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/autowire-excluded.neon');
		$container = $configurator->createContainer();

		self::assertInstanceOf(TestService::class, $container->getByType(TestService::class));

		$this->expectException(MissingServiceException::class);
		$container->getByType(stdClass::class);
	}

	public function testOnCompile(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->onCompile[] = static function (Compiler $compiler): void {
			$compiler->addConfig(['parameters' => ['test' => 'test']]);
		};

		$container = $configurator->createContainer();
		self::assertSame('test', $container->getParameters()['test']);
	}

	public function testPriority(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);

		$configurator->addConfig(__DIR__ . '/priority-parameters.neon');
		$configurator->addStaticParameters([
			'p1' => 'static',
			'p2' => 'static',
		]);
		$configurator->onCompile[] = static function (Compiler $compiler): void {
			$compiler->addConfig([
				'parameters' => [
					'p2' => 'compiler',
				],
			]);
		};

		$container = $configurator->createContainer();
		$parameters = $container->getParameters();

		self::assertSame('static', $parameters['p1']);
		self::assertSame('compiler', $parameters['p2']);
		self::assertSame('file', $parameters['p3']);
	}

	public function testConfigOverridesDefaultParameter(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->addConfig(__DIR__ . '/config/default-parameters.neon');

		$parameters = $configurator->createContainer()->getParameters();

		self::assertSame("$this->rootDir/www", $parameters['wwwDir']);
	}

	public function testStaticParameterWithDefaultValueWinsOverConfig(): void
	{
		$default = new ManualConfigurator($this->rootDir);
		$default->setForceReloadContainer();
		$default->addStaticParameters(['__unique' => __METHOD__]);
		$default->addConfig(__DIR__ . '/config/default-parameters.neon');

		$explicit = new ManualConfigurator($this->rootDir);
		$explicit->setForceReloadContainer();
		$explicit->addStaticParameters(['__unique' => __METHOD__]);
		$explicit->addStaticParameters(['wwwDir' => "$this->rootDir/public"]);
		$explicit->addConfig(__DIR__ . '/config/default-parameters.neon');

		self::assertSame("$this->rootDir/www", $default->createContainer()->getParameters()['wwwDir']);
		self::assertSame("$this->rootDir/public", $explicit->createContainer()->getParameters()['wwwDir']);
	}

	public function testConfigOverridesBaseUrl(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->addConfig(__DIR__ . '/config/baseUrl.neon');
		$configurator->addConfig(__DIR__ . '/config/baseUrl-parameter.neon');

		$parameters = $configurator->createContainer()->getParameters();

		self::assertSame('https://cli.example.com', $parameters['baseUrl']);
	}

	/**
	 * @dataProvider provideConfiguratorParameter
	 */
	public function testConfigCannotOverrideConfiguratorParameter(string $parameter): void
	{
		$file = VFS::register() . '://c.neon';
		file_put_contents($file, "parameters:\n\t$parameter: config");

		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__ . $parameter]);
		$configurator->addConfig($file);

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(
			<<<MSG
Context: Loading config file '$file'.
Problem: Parameter '$parameter' can be changed only via configurator.
Solution: Remove the parameter from config.
MSG,
		);

		$configurator->createContainer();
	}

	/**
	 * @return Generator<array<mixed>>
	 */
	public function provideConfiguratorParameter(): Generator
	{
		yield ['rootDir'];
		yield ['buildDir'];
		yield ['logDir'];
		yield ['debugMode'];
		yield ['productionMode'];
		yield ['consoleMode'];
	}

	public function testConfigCannotOverrideConfiguratorParameterInInclude(): void
	{
		$dir = VFS::register() . '://dir';
		mkdir($dir);
		file_put_contents("$dir/c.neon", "includes:\n\t- i.neon");
		file_put_contents("$dir/i.neon", "parameters:\n\tdebugMode: true");

		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->addConfig("$dir/c.neon");

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(
			<<<MSG
Context: Loading config file '$dir/i.neon'.
Problem: Parameter 'debugMode' can be changed only via configurator.
Solution: Remove the parameter from config.
MSG,
		);

		$configurator->createContainer();
	}

	/**
	 * @param array<int, string> $configFiles
	 *
	 * @dataProvider provideParameterUsedInIncludes
	 */
	public function testConfigCannotOverrideParameterUsedInIncludes(array $configFiles): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		foreach ($configFiles as $configFile) {
			$configurator->addConfig($configFile);
		}

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage("Problem: Parameter 'appDir' is changed by config and used by includes.");

		$configurator->createContainer();
	}

	/**
	 * @return Generator<array<mixed>>
	 */
	public function provideParameterUsedInIncludes(): Generator
	{
		yield [
			[
				__DIR__ . '/config/appDir-parameter.neon',
				__DIR__ . '/config/appDir-include.neon',
			],
		];

		yield [
			[
				__DIR__ . '/config/appDir-include.neon',
				__DIR__ . '/config/appDir-parameter.neon',
			],
		];
	}

	public function testStaticParameterUsedInIncludesIsNotOverriddenByConfig(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->addStaticParameters(['appDir' => "$this->rootDir/src"]);
		$configurator->addConfig(__DIR__ . '/config/appDir-parameter.neon');
		$configurator->addConfig(__DIR__ . '/config/appDir-include.neon');

		$parameters = $configurator->createContainer()->getParameters();

		self::assertSame("$this->rootDir/src", $parameters['appDir']);
		self::assertSame("$this->rootDir/www", $parameters['wwwDir']);
	}

	public function testConfigIsLoadedOnce(): void
	{
		$adapter = new class implements Adapter {

			public int $loads = 0;

			public function load(string $file): array
			{
				$this->loads++;

				return [];
			}

		};

		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->addConfigAdapter('counted', $adapter);
		$configurator->addConfig(__DIR__ . '/config/loaded-once.counted');

		$configurator->createContainer();

		self::assertSame(1, $adapter->loads);
	}

	/**
	 * @param mixed $value
	 *
	 * @dataProvider provideRejectedStaticParameter
	 */
	public function testStaticParameterIsRejected(string $parameter, $value, string $problem): void
	{
		$configurator = new ManualConfigurator($this->rootDir);

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage("Problem: $problem");

		$configurator->addStaticParameters([$parameter => $value]);
	}

	/**
	 * @return Generator<array<mixed>>
	 */
	public function provideRejectedStaticParameter(): Generator
	{
		yield ['debugMode', true, "Parameter 'debugMode' can be changed only via setDebugMode()."];
		yield ['productionMode', false, "Parameter 'productionMode' can be changed only via setDebugMode()."];
		yield ['rootDir', '/root', "Parameter 'rootDir' can be changed only via constructor."];
		yield ['appDir', 1, "Parameter 'appDir' must be string, int given."];
		yield ['buildDir', null, "Parameter 'buildDir' must be string, null given."];
		yield ['dataDir', 1, "Parameter 'dataDir' must be string, int given."];
		yield ['logDir', 1, "Parameter 'logDir' must be string, int given."];
		yield ['tempDir', 1, "Parameter 'tempDir' must be string, int given."];
		yield ['vendorDir', 1, "Parameter 'vendorDir' must be string, int given."];
		yield ['wwwDir', 1, "Parameter 'wwwDir' must be string, int given."];
		yield ['consoleMode', 'yes', "Parameter 'consoleMode' must be bool, string given."];
	}

	/**
	 * @dataProvider provideOverridableDirParameter
	 */
	public function testDynamicParameterTypeIsValidated(string $parameter): void
	{
		$configurator = new ManualConfigurator($this->rootDir);

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage("Problem: Parameter '$parameter' must be string, int given.");

		$configurator->addDynamicParameters([$parameter => 1]);
	}

	/**
	 * @dataProvider provideOverridableDirParameter
	 */
	public function testConfigParameterTypeIsValidated(string $parameter): void
	{
		$file = VFS::register() . '://c.neon';
		file_put_contents($file, "parameters:\n\tnumber: 1\n\t$parameter: %number%");

		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__ . $parameter]);
		$configurator->addConfig($file);

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage("Problem: Parameter '$parameter' must be string, int given.");

		$configurator->createContainer();
	}

	/**
	 * @return Generator<array<mixed>>
	 */
	public function provideOverridableDirParameter(): Generator
	{
		yield ['appDir'];
		yield ['dataDir'];
		yield ['tempDir'];
		yield ['vendorDir'];
		yield ['wwwDir'];
	}

	/**
	 * @param mixed $value
	 *
	 * @dataProvider provideChangedConfiguratorParameter
	 */
	public function testOnCompileCannotChangeConfiguratorParameter(string $parameter, $value): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__ . $parameter]);
		$configurator->onCompile[] = static function (Compiler $compiler) use ($parameter, $value): void {
			$compiler->addConfig(['parameters' => [$parameter => $value]]);
		};

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage("Problem: Configurator parameter '$parameter' was changed during compilation.");

		$configurator->createContainer();
	}

	/**
	 * @return Generator<array<mixed>>
	 */
	public function provideChangedConfiguratorParameter(): Generator
	{
		yield ['rootDir', '/changed'];
		yield ['buildDir', '/changed'];
		yield ['logDir', '/changed'];
		yield ['debugMode', true];
		yield ['productionMode', false];
		yield ['consoleMode', PHP_SAPI !== 'cli'];
	}

	public function testExtensionCannotChangeConfiguratorParameter(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->onCompile[] = static function (Compiler $compiler): void {
			$compiler->addExtension('changing', new class extends CompilerExtension {

				public function beforeCompile(): void
				{
					$this->getContainerBuilder()->parameters['debugMode'] = true;
				}

			});
		};

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage("Problem: Configurator parameter 'debugMode' was changed during compilation.");

		$configurator->createContainer();
	}

	public function testConfiguratorParameterWithEscapedCharacters(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->addStaticParameters(['logDir' => '@log/100%']);

		$parameters = $configurator->createContainer()->getParameters();

		self::assertSame('@@log/100%', $parameters['logDir']);
	}

	public function testCustomConfigAdapterIsGuarded(): void
	{
		$adapter = new class implements Adapter {

			public function load(string $file): array
			{
				return ['parameters' => ['debugMode' => true]];
			}

		};

		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->addConfigAdapter('counted', $adapter);
		$configurator->addConfig(__DIR__ . '/config/loaded-once.counted');

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage("Problem: Parameter 'debugMode' can be changed only via configurator.");

		$configurator->createContainer();
	}

	/**
	 * @dataProvider provideConfiguratorParameter
	 */
	public function testDynamicParameterIsRejected(string $parameter): void
	{
		$configurator = new ManualConfigurator($this->rootDir);

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage("Problem: Configurator parameter '$parameter' cannot be dynamic.");

		$configurator->addDynamicParameters([$parameter => 'dynamic']);
	}

	public function testInitialize(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/initialize.neon');

		$container = $configurator->createContainer();
		$parameters = $container->getParameters();

		self::assertArrayHasKey('initializeCallingExtension', $parameters);
		self::assertSame('called', $parameters['initializeCallingExtension']);
	}

	public function testNotInitialize(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->addConfig(__DIR__ . '/initialize.neon');

		$container = $configurator->createContainer(false);
		$parameters = $container->getParameters();

		self::assertArrayNotHasKey('initializeCallingExtension', $parameters);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testTracyOptional(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();

		DependenciesTester::addIgnoredPackages(['tracy/tracy']);

		$exception = null;
		try {
			$configurator->enableDebugger();
		} catch (PackageRequired $exception) {
			// handled below
		}

		self::assertNotNull($exception);
		self::assertSame(
			['tracy/tracy'],
			$exception->getPackages(),
		);
	}

	public function testBaseUrl(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->setForceReloadContainer();

		$configurator->addConfig(__DIR__ . '/config/baseUrl.neon');

		$container = $configurator->createContainer();
		$parameters = $container->getParameters();

		self::assertSame('https://example.com', $parameters['baseUrl']);
	}

}
