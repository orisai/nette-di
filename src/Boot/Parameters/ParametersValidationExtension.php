<?php declare(strict_types = 1);

namespace OriNette\DI\Boot\Parameters;

use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Exceptions\Message;
use function array_key_exists;
use function get_debug_type;
use function is_object;
use function is_string;
use function str_replace;

/**
 * @internal
 */
final class ParametersValidationExtension extends CompilerExtension
{

	/** @var array<string, mixed> */
	private array $configuratorParameters;

	/** @var array<string, string> */
	private array $parameterTypes;

	/**
	 * @param array<string, mixed>  $configuratorParameters escaped by Nette\DI\Helpers::escape()
	 * @param array<string, string> $parameterTypes
	 */
	public function __construct(array $configuratorParameters, array $parameterTypes)
	{
		$this->configuratorParameters = $configuratorParameters;
		$this->parameterTypes = $parameterTypes;
	}

	public function afterCompile(ClassType $class): void
	{
		$parameters = $this->getContainerBuilder()->parameters;

		foreach ($this->configuratorParameters as $name => $expected) {
			if (is_string($expected)) {
				$expected = str_replace('%%', '%', $expected);
			}

			if (!array_key_exists($name, $parameters) || $parameters[$name] !== $expected) {
				$this->throwInvalidParameter(
					"Configurator parameter '$name' was changed during compilation.",
					'Change the parameter only via configurator.',
				);
			}
		}

		foreach ($this->parameterTypes as $name => $type) {
			if (!array_key_exists($name, $parameters) || is_object($parameters[$name])) {
				continue;
			}

			$givenType = get_debug_type($parameters[$name]);
			if ($givenType !== $type) {
				$this->throwInvalidParameter(
					"Parameter '$name' must be $type, $givenType given.",
					"Use value of type $type.",
				);
			}
		}
	}

	/**
	 * @return never
	 */
	private function throwInvalidParameter(string $problem, string $solution): void
	{
		$message = Message::create()
			->withContext('Compiling DI container.')
			->withProblem($problem)
			->withSolution($solution);

		throw InvalidState::create()
			->withMessage($message);
	}

}
