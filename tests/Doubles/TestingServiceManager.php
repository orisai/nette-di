<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

use OriNette\DI\Services\ServiceManager;

final class TestingServiceManager extends ServiceManager
{

	/**
	 * {@inheritDoc}
	 */
	public function hasService($key): bool
	{
		return parent::hasService($key);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getService($key): ?object
	{
		return parent::getService($key);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTypedService($key, string $type): ?object
	{
		return parent::getTypedService($key, $type);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTypedServiceOrThrow($key, string $type): object
	{
		return parent::getTypedServiceOrThrow($key, $type);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getServiceName($key): string
	{
		return parent::getServiceName($key);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getKeys(): array
	{
		return parent::getKeys();
	}

	/**
	 * {@inheritDoc}
	 */
	public function throwMissingService($key, string $expectedType): void
	{
		parent::throwMissingService($key, $expectedType);
	}

	/**
	 * {@inheritDoc}
	 */
	public function throwInvalidServiceType($key, string $expectedType, object $service): void
	{
		parent::throwInvalidServiceType($key, $expectedType, $service);
	}

}
