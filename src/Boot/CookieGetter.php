<?php declare(strict_types = 1);

namespace OriNette\DI\Boot;

use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Message;
use function explode;

final class CookieGetter
{

	/**
	 * @return array<string>
	 */
	public static function fromEnv(string $envVar = 'DEBUG_COOKIE_VALUES', string $valueSeparator = ','): array
	{
		if ($valueSeparator === '') {
			$message = Message::create()
				->withContext('Trying to set empty string as cookie value separator.')
				->withProblem('Separator must be non-empty string.');

			throw InvalidArgument::create()
				->withMessage($message);
		}

		$var = $_SERVER[$envVar] ?? '';

		return explode($valueSeparator, $var);
	}

}
