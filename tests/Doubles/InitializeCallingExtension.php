<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

use Nette\DI\CompilerExtension;

final class InitializeCallingExtension extends CompilerExtension
{

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$this->initialization->addBody(
			"\$this->parameters['initializeCallingExtension'] = 'called';"
		);
	}

}
