<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use OriNette\DI\Definitions\DefinitionsLoader;
use stdClass;

/**
 * @property-read stdClass $config
 */
final class DefinitionsLoadingExtension extends CompilerExtension
{

	private bool $loadLater;

	public function __construct(bool $loadLater)
	{
		$this->loadLater = $loadLater;
	}

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'definitions' => Expect::arrayOf(
				Expect::anyOf(Expect::string(), Expect::array(), Expect::type(Statement::class)),
			),
		]);
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		if (!$this->loadLater) {
			$this->loadIt();
		}
	}

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		if ($this->loadLater) {
			$this->loadIt();
		}
	}

	private function loadIt(): void
	{
		$loader = new DefinitionsLoader($this->compiler);

		$config = $this->config;
		foreach ($config->definitions as $name => $definition) {
			$loader->loadDefinitionFromConfig($definition, $this->prefix('definition.' . $name));
		}
	}

}
