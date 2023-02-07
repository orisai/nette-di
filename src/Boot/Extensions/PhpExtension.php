<?php declare(strict_types = 1);

namespace OriNette\DI\Boot\Extensions;

use Nette\DI\CompilerExtension;
use Nette\NotSupportedException;
use Nette\Schema\DynamicParameter;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use function function_exists;

/**
 * @property-read array<string, int|float|string|bool|DynamicParameter|null> $config
 */
final class PhpExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::arrayOf(
			Expect::scalar()->dynamic()->nullable(),
			Expect::string(),
		);
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		foreach ($this->config as $name => $value) {
			if (!function_exists('ini_set')) {
				throw new NotSupportedException('Required function ini_set() is disabled.');
			}

			if ($value !== null) {
				$this->initialization->addBody('ini_set(?, (string) (?));', [$name, $value]);
			}
		}
	}

}
