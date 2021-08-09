<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use OriNette\DI\Definitions\DefinitionsLoader;
use Orisai\Exceptions\Logic\ShouldNotHappen;
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

		$this->getContainerBuilder()->addDependency(DefinitionsLoader::class);

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

		$list = [];
		foreach ($config->definitions as $name => $definition) {
			$serviceKey = $this->prefix('definition.' . $name);
			$resolved = $loader->loadDefinitionFromConfig($definition, $serviceKey);
			$list[$serviceKey] = $this->getData($resolved);
		}

		$listDef = new ServiceDefinition();
		$listDef->setFactory(DefinitionsList::class, [
			'list' => $list,
		]);
		$this->getContainerBuilder()->addDefinition($this->prefix('list'), $listDef);
	}

	/**
	 * @param mixed $def
	 * @return array<mixed>
	 */
	private function getData($def): array
	{
		if ($def instanceof Reference) {
			return [
				'type' => 'reference',
				'value' => $def->getValue(),
				'isName' => $def->isName(),
				'isType' => $def->isType(),
				'isSelf' => $def->isSelf(),
				'service' => $def,
			];
		}

		if ($def instanceof Definition) {
			return [
				'type' => 'definition',
				'name' => $def->getName(),
				'serviceType' => $def->getType(),
				'autowired' => $def->isExported(),
				'service' => $def,
			];
		}

		throw ShouldNotHappen::create();
	}

}
