<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

use Nette\DI\CompilerExtension;

final class ParametersAddingExtension extends CompilerExtension
{

	/** @var array<mixed> */
	private array $parameters;

	/**
	 * @param array<mixed> $parameters
	 */
	public function __construct(array $parameters)
	{
		$this->parameters = $parameters;
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		$this->getContainerBuilder()->parameters += $this->parameters;
	}

}
