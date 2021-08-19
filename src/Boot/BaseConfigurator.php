<?php declare(strict_types = 1);

namespace OriNette\DI\Boot;

use ArrayAccess;
use Closure;
use Composer\Autoload\ClassLoader;
use Countable;
use DateTimeImmutable;
use IteratorAggregate;
use Latte\Bridges\Tracy\BlueScreenPanel as LatteBlueScreenPanel;
use Nette\DI\Compiler;
use Nette\DI\Config\Adapter;
use Nette\DI\Config\Loader;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use Nette\DI\Helpers as DIHelpers;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Helpers as ConfigHelpers;
use Orisai\Utils\Dependencies\Dependencies;
use Orisai\Utils\Dependencies\Exception\PackageRequired;
use ReflectionClass;
use stdClass;
use Tracy\Bridges\Nette\Bridge;
use Tracy\Debugger;
use Traversable;
use function array_keys;
use function assert;
use function class_exists;
use function filemtime;
use function is_array;
use function is_subclass_of;
use function method_exists;
use function mkdir;
use const DATE_ATOM;
use const PHP_RELEASE_VERSION;
use const PHP_SAPI;
use const PHP_VERSION_ID;

/**
 * @internal
 */
abstract class BaseConfigurator
{

	protected string $rootDir;

	/**
	 * @var array<Closure>
	 * @phpstan-var array<Closure(Compiler $compiler): void>
	 */
	public array $onCompile = [];

	/** @var array<class-string> */
	public array $autowireExcludedClasses = [ArrayAccess::class, Countable::class, IteratorAggregate::class, stdClass::class, Traversable::class];

	/** @var array<string, mixed> */
	protected array $staticParameters;

	/** @var array<string, mixed> */
	protected array $dynamicParameters = [];

	/** @var array<string, object> */
	protected array $services = [];

	/** @var array<string, Adapter> */
	protected array $configAdapters = [];

	public function __construct(string $rootDir)
	{
		$this->rootDir = $rootDir;
		$this->staticParameters = $this->getDefaultParameters();
	}

	/**
	 * @return array<mixed>
	 */
	protected function getDefaultParameters(): array
	{
		/** @infection-ignore-all */
		return [
			'rootDir' => $this->rootDir,
			'appDir' => $this->rootDir . '/src',
			'buildDir' => $this->rootDir . '/var/build',
			'dataDir' => $this->rootDir . '/data',
			'logDir' => $this->rootDir . '/var/log',
			'tempDir' => $this->rootDir . '/var/cache',
			'vendorDir' => $this->rootDir . '/vendor',
			'wwwDir' => $this->rootDir . '/public',
			'debugMode' => false,
			'consoleMode' => PHP_SAPI === 'cli',
		];
	}

	public function isConsoleMode(): bool
	{
		return $this->staticParameters['consoleMode'];
	}

	public function isDebugMode(): bool
	{
		return $this->staticParameters['debugMode'];
	}

	public function setDebugMode(bool $debugMode): void
	{
		$this->staticParameters['debugMode'] = $debugMode;
	}

	public function enableDebugger(): void
	{
		if (!Dependencies::isPackageLoaded('tracy/tracy')) {
			throw PackageRequired::forMethod(['tracy/tracy'], static::class, __FUNCTION__);
		}

		@mkdir($this->staticParameters['logDir'], 0_777, true);
		Debugger::$strictMode = true;
		Debugger::enable(
			$this->isDebugMode() ? Debugger::DEVELOPMENT : Debugger::PRODUCTION,
			$this->staticParameters['logDir'],
		);
		/** @infection-ignore-all */
		Bridge::initialize();
		/** @infection-ignore-all */
		if (class_exists(LatteBlueScreenPanel::class)) {
			LatteBlueScreenPanel::initialize();
		}
	}

	/**
	 * @param array<string, mixed> $parameters
	 */
	public function addStaticParameters(array $parameters): self
	{
		$merged = ConfigHelpers::merge($parameters, $this->staticParameters);
		assert(is_array($merged));

		$this->staticParameters = $merged;

		return $this;
	}

	/**
	 * @param array<string, mixed> $parameters
	 */
	public function addDynamicParameters(array $parameters): self
	{
		$this->dynamicParameters = $parameters + $this->dynamicParameters;

		return $this;
	}

	/**
	 * @param array<string, object> $services
	 */
	public function addServices(array $services): self
	{
		$this->services = $services + $this->services;

		return $this;
	}

	/**
	 * @param array<string> $configFiles
	 */
	private function generateContainer(Compiler $compiler, array $configFiles): void
	{
		$loader = new Loader();
		$loader->setParameters($this->staticParameters);

		foreach ($this->configAdapters as $extension => $adapter) {
			$loader->addAdapter($extension, $adapter);
		}

		foreach ($configFiles as $configFile) {
			$compiler->loadConfig($configFile, $loader);
		}

		$now = new DateTimeImmutable();

		$parameters = DIHelpers::escape($this->staticParameters) +
			[
				'container' => [
					'compiledAtTimestamp' => (int) $now->format('U'),
					'compiledAt' => $now->format(DATE_ATOM),
					'className' => new PhpLiteral('static::class'),
				],
			];
		$compiler->addConfig(['parameters' => $parameters]);
		$compiler->setDynamicParameterNames(array_keys($this->dynamicParameters));

		$builder = $compiler->getContainerBuilder();
		$builder->addExcludedClasses($this->autowireExcludedClasses);

		$compiler->addExtension('extensions', new ExtensionsExtension());

		$this->onCompile($compiler);
	}

	private function onCompile(Compiler $compiler): void
	{
		foreach ($this->onCompile as $event) {
			$event($compiler);
		}
	}

	/**
	 * @return array<string>
	 */
	abstract protected function loadConfigFiles(): array;

	/**
	 * @return class-string<Container>
	 */
	public function loadContainer(): string
	{
		$loader = new ContainerLoader(
			/** @infection-ignore-all */
			$this->staticParameters['buildDir'] . '/orisai.di.configurator',
			$this->staticParameters['debugMode'],
		);

		$configFiles = $this->loadConfigFiles();

		$containerClass = $loader->load(
			function (Compiler $compiler) use ($configFiles): void {
				$this->generateContainer($compiler, $configFiles);
			},
			/** @infection-ignore-all */
			[
				$this->staticParameters,
				array_keys($this->dynamicParameters),
				$configFiles,
				PHP_VERSION_ID - PHP_RELEASE_VERSION,
				class_exists(ClassLoader::class)
					? filemtime(
						(new ReflectionClass(ClassLoader::class))->getFileName(),
					)
					: null,
			],
		);
		assert(is_subclass_of($containerClass, Container::class));

		return $containerClass;
	}

	public function createContainer(): Container
	{
		$containerClass = $this->loadContainer();
		$container = new $containerClass($this->dynamicParameters);

		foreach ($this->services as $name => $service) {
			$container->addService($name, $service);
		}

		assert(method_exists($container, 'initialize'));
		$container->initialize();

		return $container;
	}

}
