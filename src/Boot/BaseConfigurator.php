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
use Nette\PhpGenerator\Literal;
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
use function is_file;
use function is_subclass_of;
use function method_exists;
use function mkdir;
use function unlink;
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
	 * @var array<int|string, Closure>
	 * @phpstan-var array<int|string, Closure(Compiler $compiler): void>
	 */
	public array $onCompile = [];

	/** @var array<int|string, class-string> */
	public array $autowireExcludedClasses = [ArrayAccess::class, Countable::class, IteratorAggregate::class, stdClass::class, Traversable::class];

	/** @var array<string, mixed> */
	protected array $staticParameters;

	/** @var array<string, mixed> */
	protected array $dynamicParameters = [];

	/** @var array<string, object> */
	protected array $services = [];

	/** @var array<string, Adapter> */
	protected array $configAdapters = [];

	private bool $forceReloadContainer = false;

	public function __construct(string $rootDir)
	{
		$this->rootDir = $rootDir;
		$this->staticParameters = $this->getDefaultParameters();
	}

	/**
	 * @return array<string, mixed>
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
		/** @var array<string, mixed> $merged */
		$merged = ConfigHelpers::merge($parameters, $this->staticParameters);

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

	public function setForceReloadContainer(bool $force = true): self
	{
		$this->forceReloadContainer = $force;

		return $this;
	}

	/**
	 * @param array<int|string, string> $configFiles
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
					'className' => new Literal('static::class'),
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
		foreach ($this->onCompile as $cb) {
			$cb($compiler);
		}
	}

	/**
	 * @return array<int|string, string>
	 */
	abstract protected function loadConfigFiles(): array;

	/**
	 * @return class-string<Container>
	 */
	public function loadContainer(): string
	{
		/** @infection-ignore-all */
		$buildDir = $this->staticParameters['buildDir'] . '/orisai.di.configurator';

		/** @infection-ignore-all */
		$loader = new ContainerLoader(
			$buildDir,
			$this->staticParameters['debugMode'],
		);

		$configFiles = $this->loadConfigFiles();
		$containerKey = $this->getContainerKey($configFiles);

		$this->reloadContainerOnDemand($loader, $containerKey, $buildDir);

		$containerClass = $loader->load(
			fn (Compiler $compiler) => $this->generateContainer($compiler, $configFiles),
			$containerKey,
		);
		assert(is_subclass_of($containerClass, Container::class));

		return $containerClass;
	}

	public function createContainer(bool $initialize = true): Container
	{
		$containerClass = $this->loadContainer();
		$container = new $containerClass($this->dynamicParameters);

		foreach ($this->services as $name => $service) {
			$container->addService($name, $service);
		}

		assert(method_exists($container, 'initialize'));
		if ($initialize) {
			$container->initialize();
		}

		return $container;
	}

	/**
	 * @param array<int|string, string> $configFiles
	 * @return array<int|string, mixed>
	 */
	private function getContainerKey(array $configFiles): array
	{
		/** @infection-ignore-all */
		return [
			$this->staticParameters,
			array_keys($this->dynamicParameters),
			$configFiles,
			PHP_VERSION_ID - PHP_RELEASE_VERSION,
			class_exists(ClassLoader::class)
				? filemtime(
					(new ReflectionClass(ClassLoader::class))->getFileName(),
				)
				: null,
		];
	}

	/**
	 * @param array<int|string, mixed> $containerKey
	 */
	private function reloadContainerOnDemand(ContainerLoader $loader, array $containerKey, string $buildDir): void
	{
		$this->forceReloadContainer
		&& !class_exists($containerClass = $loader->getClassName($containerKey), false)
		&& is_file($file = "$buildDir/$containerClass.php")
		&& @unlink($file);
	}

}
