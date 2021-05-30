<?php declare(strict_types = 1);

namespace OriNette\DI\Services;

use Nette\DI\Container;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Message;
use Orisai\Utils\Classes;
use function array_key_exists;
use function array_keys;
use function get_class;

abstract class ServiceManager
{

	/** @var array<string, string> */
	private array $serviceMap;

	private Container $container;

	/**
	 * @param array<string, string> $serviceMap
	 */
	final public function __construct(array $serviceMap, Container $container)
	{
		$this->serviceMap = $serviceMap;
		$this->container = $container;
	}

	protected function hasService(string $key): bool
	{
		return array_key_exists($key, $this->serviceMap);
	}

	protected function getService(string $key): ?object
	{
		$serviceName = $this->serviceMap[$key] ?? null;
		if ($serviceName === null) {
			return null;
		}

		return $this->container->getService($serviceName);
	}

	protected function getServiceName(string $key): string
	{
		return $this->serviceMap[$key];
	}

	/**
	 * @return array<string>
	 */
	protected function getKeys(): array
	{
		return array_keys($this->serviceMap);
	}

	/**
	 * @param class-string $expectedClass
	 * @return never-return
	 */
	protected function throwMissingService(string $key, string $expectedClass): void
	{
		$selfClass = static::class;
		$className = Classes::getShortName($selfClass);

		$message = Message::create()
			->withContext("Trying to get service by key $key from $selfClass.")
			->withProblem("No service is registered under that key but service of type $expectedClass is required.")
			->withSolution("Add service with key $key to $className.");

		throw InvalidArgument::create()
			->withMessage($message);
	}

	/**
	 * @param class-string $expectedClass
	 * @return never-return
	 */
	protected function throwInvalidServiceType(string $key, string $expectedClass, object $service): void
	{
		$serviceClass = get_class($service);
		$serviceName = $this->getServiceName($key);
		$selfClass = static::class;
		$className = Classes::getShortName($selfClass);

		$message = Message::create()
			->withContext("Service $serviceName returns instance of $serviceClass.")
			->withProblem("$selfClass supports only instances of $expectedClass.")
			->withSolution("Remove service from $className or make the service return supported object type.");

		throw InvalidArgument::create()
			->withMessage($message);
	}

}
