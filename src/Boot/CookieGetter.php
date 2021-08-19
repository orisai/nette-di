<?php declare(strict_types = 1);

namespace OriNette\DI\Boot;

use Nette\Utils\Strings;
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
		if (!isset($_SERVER[$variableName])) {
			return [];
		}

		return self::filterEmpty(explode($valueSeparator, $_SERVER[$variableName]));
	}

	/**
	 * @param array<string> $values
	 * @return array<string>
	 */
	private static function filterEmpty(array $values): array
	{
		foreach ($values as $key => $value) {
			$value = Strings::trim($value);

			if ($value !== '') {
				$values[$key] = $value;
			} else {
				unset($values[$key]);
			}
		}

		return $values;
	}

}
