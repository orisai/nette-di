<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

use OriNette\DI\Services\ServiceManager;

final class TestingServiceManager extends ServiceManager
{

	public function hasService($key): bool
	{
		return parent::hasService($key);
	}

	public function getService($key): ?object
	{
		return parent::getService($key);
	}

	public function getTypedService($key, string $type): ?object
	{
		return parent::getTypedService($key, $type);
	}

	public function getTypedServiceOrThrow($key, string $type): object
	{
		return parent::getTypedServiceOrThrow($key, $type);
	}

	public function getServiceName($key): string
	{
		return parent::getServiceName($key);
	}

	public function getKeys(): array
	{
		return parent::getKeys();
	}

	public function throwMissingService($key, string $expectedType): void
	{
		parent::throwMissingService($key, $expectedType);
	}

	public function throwInvalidServiceType($key, string $expectedType, object $service): void
	{
		parent::throwInvalidServiceType($key, $expectedType, $service);
	}

}
