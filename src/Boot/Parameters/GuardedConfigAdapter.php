<?php declare(strict_types = 1);

namespace OriNette\DI\Boot\Parameters;

use Nette\DI\Config\Adapter;

/**
 * @internal
 */
final class GuardedConfigAdapter implements Adapter
{

	private Adapter $adapter;

	private ConfigParametersGuard $guard;

	public function __construct(Adapter $adapter, ConfigParametersGuard $guard)
	{
		$this->adapter = $adapter;
		$this->guard = $guard;
	}

	public function load(string $file): array
	{
		$config = $this->adapter->load($file);
		$this->guard->check($file, $config);

		return $config;
	}

}
