<?php declare(strict_types = 1);

namespace OriNette\DI\Services;

use Nette\DI\Container;
use Orisai\Exceptions\Message;
use Orisai\Utils\Classes;
use function array_key_exists;
use function array_keys;
use function get_class;

abstract class ServiceManager
{

	/** @var array<int|string, string> */
	private array $serviceMap;

	private Container $container;

	/**
	 * @param array<int|string, string> $serviceMap
	 */
	final public function __construct(array $serviceMap, Container $container)
	{
		$this->serviceMap = $serviceMap;
		$this->container = $container;
	}

	/**
	 * @param int|string $key
	 */
	protected function hasService($key): bool
	{
		return array_key_exists($key, $this->serviceMap);
	}

	/**
	 * @param int|string $key
	 */
	protected function getService($key): ?object
	{
		$serviceName = $this->serviceMap[$key] ?? null;
		if ($serviceName === null) {
			return null;
		}

		return $this->container->getService($serviceName);
	}

	/**
	 * @param int|string $key
	 */
	protected function getServiceName($key): string
	{
		return $this->serviceMap[$key];
	}

	/**
	 * @return array<int|string>
	 */
	protected function getKeys(): array
	{
		return array_keys($this->serviceMap);
	}

	/**
	 * @param int|string $key
	 * @param class-string $expectedClass
	 * @return never
	 */
	protected function throwMissingService($key, string $expectedClass): void
	{
		$selfClass = static::class;
		$className = Classes::getShortName($selfClass);

		$message = Message::create()
			->withContext("Trying to get service by key $key from $selfClass.")
			->withProblem("No service is registered under that key but service of type $expectedClass is required.")
			->withSolution("Add service with key $key to $className.");

		throw MissingService::create()
			->withMessage($message);
	}

	/**
	 * @param int|string $key
	 * @param class-string $expectedClass
	 * @return never
	 */
	protected function throwInvalidServiceType($key, string $expectedClass, object $service): void
	{
		$serviceClass = get_class($service);
		$serviceName = $this->getServiceName($key);
		$selfClass = static::class;
		$className = Classes::getShortName($selfClass);

		$message = Message::create()
			->withContext("Service $serviceName returns instance of $serviceClass.")
			->withProblem("$selfClass supports only instances of $expectedClass.")
			->withSolution("Remove service from $className or make the service return supported object type.");

		throw MissingService::create()
			->withMessage($message);
	}

}
