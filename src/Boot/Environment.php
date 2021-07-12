<?php declare(strict_types = 1);

namespace OriNette\DI\Boot;

use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Message;
use function array_key_exists;
use function array_shift;
use function count;
use function explode;
use function in_array;
use function php_uname;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

final class Environment
{

	public static function isEnvDebugMode(string $variableName = 'ORISAI_DEBUG'): bool
	{
		$debug = $_SERVER[$variableName] ?? null;

		return $debug !== null && (strtolower($debug) === 'true' || $debug === '1');
	}

	/**
	 * Collect environment parameters prefixed by $prefix
	 *
	 * @return array<mixed>
	 */
	public static function loadEnvParameters(string $prefix = 'ORISAI', string $delimiter = '__'): array
	{
		if ($delimiter === '') {
			$message = Message::create()
				->withContext('Trying to set empty string as env parameter delimiter.')
				->withProblem('Delimiter must be non-empty string.');

			throw InvalidArgument::create()
				->withMessage($message);
		}

		if ($prefix !== '') {
			$prefix .= $delimiter;
		}

		$map = static function (array &$array, array $keys, $value) use (&$map) {
			if (count($keys) <= 0) {
				return $value;
			}

			$key = array_shift($keys);

			if (!array_key_exists($key, $array)) {
				$array[$key] = [];
			}

			// Recursive
			$array[$key] = $map($array[$key], $keys, $value);

			return $array;
		};

		$parameters = [];

		foreach ($_SERVER as $key => $value) {
			if ($prefix === '' || strpos($key, $prefix) === 0) {
				// Parse PREFIX{delimiter}{NAME-1}{delimiter}{NAME-N}
				$keys = explode($delimiter, strtolower(substr($key, strlen($prefix))));
				// Make array structure
				$map($parameters, $keys, $value);
			}
		}

		return $parameters;
	}

	/**
	 * @param array<string> $values
	 */
	public static function hasCookie(array $values, string $cookieName = 'orisai-debug'): bool
	{
		$cookie = $_COOKIE[$cookieName] ?? null;

		if ($cookie === null) {
			return false;
		}

		return in_array($cookie, $values, true);
	}

	public static function isLocalhost(): bool
	{
		$list = [];

		// Forwarded for BC, X-Forwarded-For is standard
		if (!isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !isset($_SERVER['HTTP_FORWARDED'])) {
			$list[] = '127.0.0.1';
			$list[] = '::1';
		}

		$address = $_SERVER['REMOTE_ADDR'] ?? php_uname('n');

		return in_array($address, $list, true);
	}

}
