<?php declare(strict_types = 1);

namespace OriNette\DI\Boot\Parameters;

use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Exceptions\Message;
use function array_key_exists;
use function is_array;
use function is_string;
use function preg_match_all;

/**
 * @internal
 */
final class ConfigParametersGuard
{

	/** @var array<int, string> */
	private array $configuratorParameters;

	/** @var array<int, string> */
	private array $overridableParameters;

	/** @var array<string, string> */
	private array $changedBy = [];

	/** @var array<string, string> */
	private array $includedBy = [];

	/**
	 * @param array<int, string> $configuratorParameters
	 * @param array<int, string> $overridableParameters
	 */
	public function __construct(array $configuratorParameters, array $overridableParameters)
	{
		$this->configuratorParameters = $configuratorParameters;
		$this->overridableParameters = $overridableParameters;
	}

	/**
	 * @param array<mixed> $config
	 */
	public function check(string $file, array $config): void
	{
		$parameters = $config['parameters'] ?? null;
		if (!is_array($parameters)) {
			$parameters = [];
		}

		foreach ($this->configuratorParameters as $name) {
			if (array_key_exists($name, $parameters)) {
				$message = Message::create()
					->withContext("Loading config file '$file'.")
					->withProblem("Parameter '$name' can be changed only via configurator.")
					->withSolution('Remove the parameter from config.');

				throw InvalidState::create()
					->withMessage($message);
			}
		}

		$included = $this->getParametersUsedByIncludes($config);

		foreach ($this->overridableParameters as $name) {
			if (array_key_exists($name, $parameters)) {
				$this->changedBy[$name] ??= $file;
			}

			if (isset($included[$name])) {
				$this->includedBy[$name] ??= $file;
			}

			if (isset($this->changedBy[$name], $this->includedBy[$name])) {
				$message = Message::create()
					->withContext(
						"Loading config file '{$this->changedBy[$name]}' which changes the parameter"
						. " and '{$this->includedBy[$name]}' which uses it in includes.",
					)
					->withProblem("Parameter '$name' is changed by config and used by includes.")
					->withSolution('Change the parameter via configurator or do not use it in includes.');

				throw InvalidState::create()
					->withMessage($message);
			}
		}
	}

	/**
	 * @param array<mixed> $config
	 * @return array<string, true>
	 */
	private function getParametersUsedByIncludes(array $config): array
	{
		$includes = $config['includes'] ?? null;
		if (!is_array($includes)) {
			return [];
		}

		$used = [];
		foreach ($includes as $include) {
			if (!is_string($include)) {
				continue;
			}

			preg_match_all('#%([\w-]+)[\w.-]*%#', $include, $matches);
			foreach ($matches[1] as $name) {
				$used[$name] = true;
			}
		}

		return $used;
	}

}
