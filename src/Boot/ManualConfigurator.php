<?php declare(strict_types = 1);

namespace OriNette\DI\Boot;

final class ManualConfigurator extends BaseConfigurator
{

	/** @var array<string> */
	private array $configs = [];

	public function addConfig(string $configFile): void
	{
		$this->configs[] = $configFile;
	}

	/**
	 * @return array<string>
	 */
	protected function loadConfigFiles(): array
	{
		return $this->configs;
	}

}
