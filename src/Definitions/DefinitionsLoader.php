<?php declare(strict_types = 1);

namespace OriNette\DI\Definitions;

use Nette\DI\Compiler;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\LocatorDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\Resolver;
use Nette\Utils\Strings;
use function array_key_exists;
use function array_merge;
use function array_unique;
use function is_array;
use function is_string;
use function preg_replace;
use function str_replace;
use function substr;
use const SORT_REGULAR;

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

	/**
	 * @param array<Definition> $definitions
	 * @return array<ServiceDefinition>
	 */
	public function getServiceDefinitionsFromDefinitions(array $definitions): array
	{
		$definitionsByDefinition = [];
		foreach ($definitions as $definition) {
			$definitionsByDefinition[] = $this->getServiceDefinitionsFromDefinition($definition);
		}

		$serviceDefinitions = array_merge(...$definitionsByDefinition);

		// Filter out duplicates - we cannot distinguish if service from LocatorDefinition is created
		// by accessor or factory so duplicates are possible
		$serviceDefinitions = array_unique($serviceDefinitions, SORT_REGULAR);

		return $serviceDefinitions;
	}

	/**
	 * @return array<ServiceDefinition>
	 */
	private function getServiceDefinitionsFromDefinition(Definition $definition): array
	{
		if ($definition instanceof ServiceDefinition) {
			return [$definition];
		}

		if ($definition instanceof FactoryDefinition) {
			return [$definition->getResultDefinition()];
		}

		if ($definition instanceof LocatorDefinition) {
			$resolver = new Resolver($this->compiler->getContainerBuilder());

			$definitionsByReference = [];
			foreach ($definition->getReferences() as $reference) {
				// Check that reference is valid
				$reference = $resolver->normalizeReference($reference);
				// Get definition from reference
				$definition = $resolver->resolveReference($reference);
				// Only ServiceDefinition should be possible here

				$definitionsByReference[] = $this->getServiceDefinitionsFromDefinition($definition);
			}

			return array_merge(...$definitionsByReference);
		}

		// Accessor - service definition exists independently
		// Imported - runtime-created service, cannot work with
		// Unknown
		return [];
	}

}
