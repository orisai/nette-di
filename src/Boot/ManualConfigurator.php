<?php declare(strict_types = 1);

namespace OriNette\DI\Boot;

use Nette\DI\Config\Adapter;

final class ManualConfigurator extends BaseConfigurator
{

	/** @var array<int|string, string> */
	private array $configs = [];

	public function addConfig(string $configFile): void
	{
		$this->configs[] = $configFile;
	}

	public function addConfigAdapter(string $extension, Adapter $adapter): void
	{
		$this->configAdapters[$extension] = $adapter;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function loadConfigFiles(): array
	{
		return $this->configs;
	}

}
