<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

final class AnotherTestService
{

	/** @var array<mixed> */
	private array $params = [];

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

}
