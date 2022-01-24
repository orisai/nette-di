<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

use OriNette\DI\Boot\BaseConfigurator;

final class TestingConfigurator extends BaseConfigurator
{

	/**
	 * {@inheritDoc}
	 */
	public function getDefaultParameters(): array
	{
		return parent::getDefaultParameters();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function loadConfigFiles(): array
	{
		return [];
	}

}
