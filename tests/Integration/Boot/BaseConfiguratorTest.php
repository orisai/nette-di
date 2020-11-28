<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Integration\Boot;

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use OriNette\DI\Boot\ManualConfigurator;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\OriNette\DI\Doubles\ParametersAddingExtension;
use Tests\OriNette\DI\Doubles\TestService;
use Tracy\Debugger;
use function class_exists;
use function dirname;
use function is_subclass_of;
use const PHP_SAPI;

final class BaseConfiguratorTest extends TestCase
{

	private string $rootDir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->rootDir = dirname(__DIR__, 3);
	}

	public function testCreateContainer(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$class = $configurator->loadContainer();

		self::assertTrue(class_exists($class));
		self::assertTrue(is_subclass_of($class, Container::class));

		$configurator->createContainer();
	}

	public function testDebugContainer(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);

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
		$configurator = new ManualConfigurator($rootDir);

		self::assertSame(PHP_SAPI === 'cli', $configurator->isConsoleMode());

		self::assertFalse($configurator->isDebugMode());
		$configurator->setDebugMode(true);
		self::assertTrue($configurator->isDebugMode());

		$configurator->addStaticParameters(['test' => 'test', 'consoleMode' => true]);
		$configurator->addDynamicParameters(['dynamic' => 'dynamic']);

		$container = $configurator->createContainer();
		$parameters = $container->parameters;

		self::assertSame($rootDir, $parameters['rootDir']);
		self::assertSame($rootDir . '/src', $parameters['appDir']);
		self::assertSame($rootDir . '/var/build', $parameters['buildDir']);
		self::assertSame($rootDir . '/var/log', $parameters['logDir']);
		self::assertSame($rootDir . '/var/tmp', $parameters['tempDir']);
		self::assertSame($rootDir . '/vendor', $parameters['vendorDir']);
		self::assertSame($rootDir . '/public', $parameters['wwwDir']);
		self::assertTrue($parameters['debugMode']);
		self::assertTrue($parameters['consoleMode']);
		self::assertSame('test', $parameters['test']);
		self::assertSame('dynamic', $parameters['dynamic']);
		self::assertArrayHasKey('container', $parameters);
		self::assertArrayHasKey('compiledAtTimestamp', $parameters['container']);
		self::assertArrayHasKey('compiledAt', $parameters['container']);
		self::assertArrayHasKey('className', $parameters['container']);
	}

	public function testParametersSpecificContainer(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
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
		$configurator->addStaticParameters([
			'param1' => '%test%',
			'param2' => '@test',
		]);

		$container = $configurator->createContainer();
		$parameters = $container->parameters;

		self::assertSame('%test%', $parameters['param1']);
		self::assertSame('@@test', $parameters['param2']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testDebuggerProduction(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
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
		$configurator->setDebugMode(true);
		$configurator->enableDebugger();

		self::assertTrue(Debugger::$strictMode);
		self::assertSame(Debugger::$productionMode, Debugger::DEVELOPMENT);
		self::assertSame($this->rootDir . '/var/log', Debugger::$logDirectory);
	}

	public function testServices(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
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
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->addConfig(__DIR__ . '/extensions.neon');
		$configurator->onCompile[] = static function (Compiler $compiler): void {
			$compiler->addExtension('test3', new ParametersAddingExtension(['test3' => 'test3']));
		};

		$container = $configurator->createContainer();
		$parameters = $container->parameters;
		self::assertSame('test1', $parameters['test1']);
		self::assertSame('test2', $parameters['test2']);
		self::assertSame('test3', $parameters['test3']);
	}

	public function testConfigFileSpecificContainer(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
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
		$configurator->addConfig(__DIR__ . '/autowire-excluded.neon');
		$container = $configurator->createContainer();

		self::assertInstanceOf(TestService::class, $container->getByType(TestService::class));

		$this->expectException(MissingServiceException::class);
		$container->getByType(stdClass::class);
	}

	public function testOnCompile(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
		$configurator->addStaticParameters(['__unique' => __METHOD__]);
		$configurator->onCompile[] = static function (Compiler $compiler): void {
			$compiler->addConfig(['parameters' => ['test' => 'test']]);
		};

		$container = $configurator->createContainer();
		self::assertSame('test', $container->parameters['test']);
	}

	public function testPriority(): void
	{
		$configurator = new ManualConfigurator($this->rootDir);
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
		$parameters = $container->parameters;

		self::assertSame('static', $parameters['p1']);
		self::assertSame('compiler', $parameters['p2']);
		self::assertSame('file', $parameters['p3']);
	}

}
