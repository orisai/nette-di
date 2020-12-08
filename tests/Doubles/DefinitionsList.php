<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

final class DefinitionsList
{

	/** @var array<mixed> */
	private array $list;

	/**
	 * @param array<mixed> $list
	 */
	public function __construct(array $list)
	{
		$this->list = $list;
	}

	/**
	 * @return array<mixed>
	 */
	public function getList(): array
	{
		return $this->list;
	}

}
