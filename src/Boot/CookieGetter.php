<?php declare(strict_types = 1);

namespace OriNette\DI\Boot;

use function explode;

final class CookieGetter
{

	/**
	 * @param non-empty-string $variableName
	 * @param non-empty-string $valueSeparator
	 * @return array<string>
	 */
	public static function fromEnv(string $variableName = 'DEBUG_COOKIE_VALUES', string $valueSeparator = ','): array
	{
		$var = $_SERVER[$variableName] ?? '';

		return explode($valueSeparator, $var);
	}

}
