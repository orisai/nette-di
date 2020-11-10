<?php declare(strict_types = 1);

namespace OriNette\DI\Definitions;

use Nette\DI\Compiler;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Statement;
use Nette\Utils\Strings;
use function array_key_exists;
use function is_array;
use function is_string;
use function preg_replace;
use function str_replace;
use function substr;

final class DefinitionsLoader
{

	private Compiler $compiler;

	public function __construct(Compiler $compiler)
	{
		$this->compiler = $compiler;
	}

	/**
	 * @param string|array<mixed>|Statement $config
	 * @return Definition|string
	 */
	public function loadDefinitionFromConfig($config, string $preferredPrefix)
	{
		$builder = $this->compiler->getContainerBuilder();

		// Definition is defined in ServicesExtension, try to get it
		if (is_string($config) && Strings::startsWith($config, '@')) {
			$definitionName = substr($config, 1);

			// Definition is already loaded (beforeCompile phase), return it
			if ($builder->hasDefinition($definitionName)) {
				return $builder->getDefinition($definitionName);
			}

			// Definition not loaded yet (loadConfiguration phase), return reference string
			return $config;
		}

		// Raw configuration given, create definition from it
		$this->compiler->loadDefinitionsFromConfig([$preferredPrefix => self::doubleEscape($config)]);
		$definition = $builder->getDefinition($preferredPrefix);

		// Disable autowiring for class which is defined for extension only and does not have autowiring explicitly set
		if (!is_array($config) || !array_key_exists('autowired', $config)) {
			$definition->setAutowired(false);
		}

		return $definition;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private static function doubleEscape($value)
	{
		if ($value instanceof Statement) {
			$value->arguments = self::doubleEscape($value->arguments);

			return $value;
		}

		if (is_array($value)) {
			$result = [];
			foreach ($value as $key => $val) {
				$key = is_string($key) ? str_replace('%', '%%', $key) : $key;
				$result[$key] = self::doubleEscape($val);
			}

			return $result;
		}

		if (is_string($value)) {
			return preg_replace(['/@/', '/%/'], ['@@', '%%'], $value);
		}

		return $value;
	}

}
