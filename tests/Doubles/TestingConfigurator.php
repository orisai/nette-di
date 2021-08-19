<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

use OriNette\DI\Boot\BaseConfigurator;

final class TestingConfigurator extends BaseConfigurator
{

	/**
	 * @return array<mixed>
	 */
	public function getDefaultParameters(): array
	{
		return parent::getDefaultParameters();
	}

	/**
	 * @return array<string>
	 */
	protected function loadConfigFiles(): array
	{
		return [];
	}

}
