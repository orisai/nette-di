<?php declare(strict_types = 1);

namespace OriNette\DI\Boot\Extensions;

use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * @property-read array<string, mixed> $config
 */
final class ConstantsExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::arrayOf(
			Expect::anyOf(
				Expect::int(),
				Expect::float(),
				Expect::string(),
				Expect::bool(),
				Expect::null(),
				Expect::array(),
			),
			Expect::string(),
		);
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		foreach ($this->config as $name => $value) {
			$this->initialization->addBody('define(?, ?);', [$name, $value]);
		}
	}

}
