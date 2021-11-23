<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

use stdClass;

final class TestService
{

	/** @var array<mixed> */
	private array $params = [];

	private ?stdClass $ctorArgument;

	public function __construct(?stdClass $ctorArgument = null)
	{
		$this->ctorArgument = $ctorArgument;
	}

	/**
	 * @param mixed ...$params
	 */
	public function addParams(...$params): void
	{
		$this->params[] = $params;
	}

	/**
	 * @return array<mixed>
	 */
	public function getParams(): array
	{
		return $this->params;
	}

	public function getCtorArgument(): ?stdClass
	{
		return $this->ctorArgument;
	}

}
