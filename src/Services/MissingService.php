<?php declare(strict_types = 1);

namespace OriNette\DI\Services;

use Nette\DI\MissingServiceException;
use Orisai\Exceptions\Check\UncheckedException;
use Orisai\Exceptions\ConfigurableException;
use Throwable;

final class MissingService extends MissingServiceException implements UncheckedException
{

	use ConfigurableException;

	protected function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

	public static function create(): self
	{
		return new self();
	}

}
