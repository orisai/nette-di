<?php declare(strict_types = 1);

namespace OriNette\DI\Boot;

use Orisai\Installer\Loader\BaseLoader;

final class AutomaticConfigurator extends BaseConfigurator
{

	private BaseLoader $loader;

	public function __construct(string $rootDir, BaseLoader $loader)
	{
		parent::__construct($rootDir);
		$this->loader = $loader;
		$this->addStaticParameters([
			'modules' => $this->loader->loadModulesMeta($rootDir),
		]);
	}

	protected function loadConfigFiles(): array
	{
		return $this->loader->loadConfigFiles($this->rootDir);
	}

	public function loadContainer(): string
	{
		$this->loader->configureSwitch('consoleMode', $this->staticParameters['consoleMode']);
		$this->loader->configureSwitch('debugMode', $this->staticParameters['debugMode']);

		return parent::loadContainer();
	}

}
