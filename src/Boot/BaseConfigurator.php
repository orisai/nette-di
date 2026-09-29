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
use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\Config\Adapters\PhpAdapter;
use Nette\DI\Config\Loader;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Definitions\Statement;
use Nette\DI\Extensions\ExtensionsExtension;
use Nette\DI\Helpers as DIHelpers;
use Nette\Loaders\RobotLoader;
use Nette\PhpGenerator\Literal;
use Nette\Schema\Helpers as ConfigHelpers;
use OriNette\DI\Boot\Parameters\BaseUrl;
use OriNette\DI\Boot\Parameters\ConfigParametersGuard;
use OriNette\DI\Boot\Parameters\GuardedConfigAdapter;
use OriNette\DI\Boot\Parameters\ParametersValidationExtension;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Logic\NotImplemented;
use Orisai\Exceptions\Message;
use Orisai\Utils\Dependencies\Dependencies;
use Orisai\Utils\Dependencies\Exception\PackageRequired;
use ReflectionClass;
use stdClass;
use Tracy\Bridges\Nette\Bridge;
use Tracy\Debugger;
use Traversable;
use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function assert;
use function class_exists;
use function filemtime;
use function get_debug_type;
use function is_file;
use function is_subclass_of;
use function mkdir;
use function unlink;
use const DATE_ATOM;
use const PHP_RELEASE_VERSION;
use const PHP_SAPI;
use const PHP_VERSION_ID;

abstract class BaseConfigurator
{

	private const ConfiguratorParameters = [
		'rootDir',
		'buildDir',
		'logDir',
		'debugMode',
		'productionMode',
		'consoleMode',
	];

	private const DedicatedSetters = [
		'rootDir' => 'constructor',
		'debugMode' => 'setDebugMode()',
		'productionMode' => 'setDebugMode()',
	];

	private const ParameterTypes = [
		'rootDir' => 'string',
		'appDir' => 'string',
		'buildDir' => 'string',
		'dataDir' => 'string',
		'logDir' => 'string',
		'tempDir' => 'string',
		'vendorDir' => 'string',
		'wwwDir' => 'string',
		'debugMode' => 'bool',
		'productionMode' => 'bool',
		'consoleMode' => 'bool',
	];

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
	private array $overridableParameters;

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
		$this->overridableParameters = array_diff_key(
			$this->staticParameters,
			array_flip(self::ConfiguratorParameters),
		);
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
			'baseUrl' => new Statement('@' . BaseUrl::class . '::get'),
			'debugMode' => false,
			'productionMode' => true,
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
		$this->staticParameters['productionMode'] = !$debugMode;
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
	 * @throws NotImplemented if RobotLoader is not available
	 */
	public function createRobotLoader(): RobotLoader
	{
		if (!class_exists(RobotLoader::class)) {
			throw NotImplemented::create()
				->withMessage('RobotLoader not found, do you have `nette/robot-loader` package installed?');
		}

		$loader = new RobotLoader();
		$loader->setTempDirectory($this->staticParameters['buildDir'] . '/nette.robotLoader');
		$loader->setAutoRefresh($this->staticParameters['debugMode']);

		return $loader;
	}

	/**
	 * @param array<string, mixed> $parameters
	 */
	public function addStaticParameters(array $parameters): self
	{
		foreach (self::DedicatedSetters as $name => $setter) {
			if (array_key_exists($name, $parameters)) {
				$this->throwInvalidParameter(
					__FUNCTION__,
					"Parameter '$name' can be changed only via $setter.",
					"Use $setter instead.",
				);
			}
		}

		$this->checkParameterTypes(__FUNCTION__, $parameters);

		/** @var array<string, mixed> $merged */
		$merged = ConfigHelpers::merge($parameters, $this->staticParameters);

		$this->staticParameters = $merged;
		$this->overridableParameters = array_diff_key($this->overridableParameters, $parameters);

		return $this;
	}

	/**
	 * @param array<string, mixed> $parameters
	 */
	public function addDynamicParameters(array $parameters): self
	{
		foreach (self::ConfiguratorParameters as $name) {
			if (array_key_exists($name, $parameters)) {
				$setter = self::DedicatedSetters[$name] ?? 'addStaticParameters()';
				$this->throwInvalidParameter(
					__FUNCTION__,
					"Configurator parameter '$name' cannot be dynamic.",
					"Use $setter instead.",
				);
			}
		}

		$this->checkParameterTypes(__FUNCTION__, $parameters);

		$this->dynamicParameters = $parameters + $this->dynamicParameters;

		return $this;
	}

	/**
	 * @param array<string, mixed> $parameters
	 */
	private function checkParameterTypes(string $function, array $parameters): void
	{
		foreach (self::ParameterTypes as $name => $type) {
			if (!array_key_exists($name, $parameters)) {
				continue;
			}

			$givenType = get_debug_type($parameters[$name]);
			if ($givenType !== $type) {
				$this->throwInvalidParameter(
					$function,
					"Parameter '$name' must be $type, $givenType given.",
					"Use value of type $type.",
				);
			}
		}
	}

	/**
	 * @return never
	 */
	private function throwInvalidParameter(string $function, string $problem, string $solution): void
	{
		$class = static::class;

		$message = Message::create()
			->withContext("Trying to call $class->$function().")
			->withProblem($problem)
			->withSolution($solution);

		throw InvalidArgument::create()
			->withMessage($message);
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

		$guard = new ConfigParametersGuard(self::ConfiguratorParameters, array_keys($this->overridableParameters));
		$adapters = $this->configAdapters + ['neon' => new NeonAdapter(), 'php' => new PhpAdapter()];
		foreach ($adapters as $extension => $adapter) {
			$loader->addAdapter($extension, new GuardedConfigAdapter($adapter, $guard));
		}

		$parameters = DIHelpers::escape($this->staticParameters);
		$overridableParameters = array_intersect_key($parameters, $this->overridableParameters);
		$configuratorParameters = array_intersect_key($parameters, array_flip(self::ConfiguratorParameters));

		$compiler->loadConfig(__DIR__ . '/Parameters/wiring.neon');
		$compiler->addConfig(['parameters' => $overridableParameters]);
		foreach ($configFiles as $configFile) {
			$compiler->loadConfig($configFile, $loader);
		}

		$now = new DateTimeImmutable();

		$parameters = array_diff_key($parameters, $overridableParameters)
			+ [
				'container' => [
					'compiledAtTimestamp' => (int) $now->format('U'),
					'compiledAt' => $now->format(DATE_ATOM),
					'className' => new Literal('static::class'),
				],
			];
		$compiler->addConfig(['parameters' => $parameters]);
		$compiler->setDynamicParameterNames(array_merge(
			array_keys($this->dynamicParameters),
			['baseUrl'],
		),);

		$builder = $compiler->getContainerBuilder();
		$builder->addExcludedClasses($this->autowireExcludedClasses);

		$compiler->addExtension('extensions', new ExtensionsExtension());

		$this->onCompile($compiler);

		$compiler->addExtension('orisai.di.configurator', new ParametersValidationExtension(
			$configuratorParameters,
			self::ParameterTypes,
		));
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
			function (Compiler $compiler) use ($configFiles): ?string {
				$this->generateContainer($compiler, $configFiles);

				return null;
			},
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
			array_keys($this->overridableParameters),
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
