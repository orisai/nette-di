<?php declare(strict_types = 1);

namespace OriNette\DI\Services;

use Nette\DI\Container;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Message;
use Orisai\Utils\Reflection\Classes;
use function array_key_exists;
use function array_keys;
use function get_class;
use function is_a;

abstract class ServiceManager
{

	/** @var array<int|string, string> */
	private array $serviceMap;

	protected Container $container;

	/**
	 * @param array<int|string, string> $serviceMap
	 */
	public function __construct(array $serviceMap, Container $container)
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
	 * @template T of object
	 * @param int|string      $key
	 * @param class-string<T> $type
	 * @return T|null
	 */
	protected function getTypedService($key, string $type): ?object
	{
		$service = $this->getService($key);

		if ($service === null) {
			return null;
		}

		if (!is_a($service, $type)) {
			$this->throwInvalidServiceType($key, $type, $service);
		}

		return $service;
	}

	/**
	 * @template T of object
	 * @param int|string      $key
	 * @param class-string<T> $type
	 * @return T
	 */
	protected function getTypedServiceOrThrow($key, string $type): object
	{
		$service = $this->getService($key);

		if ($service === null) {
			$this->throwMissingService($key, $type);
		}

		if (!is_a($service, $type)) {
			$this->throwInvalidServiceType($key, $type, $service);
		}

		return $service;
	}

	/**
	 * @param int|string $key
	 */
	protected function getServiceName($key): string
	{
		if (!isset($this->serviceMap[$key])) {
			$class = static::class;
			$function = __FUNCTION__;

			$message = Message::create()
				->withContext("Trying to call $class->$function().")
				->withProblem("Given key '$key' has no service associated.")
				->withSolution('Call it only with key which exists in service map.');

			throw InvalidArgument::create()
				->withMessage($message);
		}

		return $this->serviceMap[$key];
	}

	/**
	 * @return array<int, int|string>
	 */
	protected function getKeys(): array
	{
		return array_keys($this->serviceMap);
	}

	/**
	 * @param int|string   $key
	 * @param class-string $expectedType
	 * @return never
	 */
	protected function throwMissingService($key, string $expectedType): void
	{
		$selfClass = static::class;
		$className = Classes::getShortName($selfClass);

		$message = Message::create()
			->withContext("Trying to get service by key '$key' from $selfClass.")
			->withProblem("No service is registered under that key but service of type $expectedType is required.")
			->withSolution("Add service with key '$key' to $className.");

		throw InvalidArgument::create()
			->withMessage($message);
	}

	/**
	 * @param int|string   $key
	 * @param class-string $expectedType
	 * @return never
	 */
	protected function throwInvalidServiceType($key, string $expectedType, object $service): void
	{
		$serviceClass = get_class($service);
		$serviceName = $this->getServiceName($key);
		$selfClass = static::class;
		$className = Classes::getShortName($selfClass);

		$message = Message::create()
			->withContext("Service '$serviceName' returns instance of $serviceClass.")
			->withProblem("$selfClass supports only instances of $expectedType.")
			->withSolution("Remove service from $className or make the service return supported object type.");

		throw InvalidArgument::create()
			->withMessage($message);
	}

}
