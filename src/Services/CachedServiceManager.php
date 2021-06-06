<?php declare(strict_types = 1);

namespace OriNette\DI\Services;

use function array_key_exists;

abstract class CachedServiceManager extends ServiceManager
{

	/** @var array<int|string, object|null> */
	private array $cache = [];

	/**
	 * @param int|string $key
	 */
	protected function getService($key): ?object
	{
		if (array_key_exists($key, $this->cache)) {
			return $this->cache[$key];
		}

		return $this->cache[$key] = parent::getService($key);
	}

}
