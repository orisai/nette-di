<?php declare(strict_types = 1);

namespace OriNette\DI\Boot;

use function array_key_exists;
use function array_shift;
use function count;
use function explode;
use function getenv;
use function in_array;
use function php_uname;
use function strlen;
use function strncmp;
use function strtolower;
use function substr;
use const PHP_SAPI;

final class Environment
{

	/** @internal */
	public const SidDebugCookie = 'orisai-debug-sid';

	/**
	 * @param non-empty-string $variableName
	 */
	public static function isEnvDebug(string $variableName = 'ORISAI_DEBUG'): bool
	{
		$debug = $_SERVER[$variableName] ?? getenv($variableName);

		return $debug !== false && (strtolower($debug) === 'true' || $debug === '1');
	}

	/**
	 * @param non-empty-string $variableName
	 *
	 * @deprecated Use isEnvDebug() instead
	 * @see        self::isEnvDebug()
	 */
	public static function isEnvDebugMode(string $variableName = 'ORISAI_DEBUG'): bool
	{
		return self::isEnvDebug($variableName);
	}

	/**
	 * Parse environment variables prefixed by $prefix via splitting parts by $delimiter
	 * {$prefix}{$delimiter}{NAME-1}({$delimiter}{NAME-n})
	 *
	 * @param non-empty-string $delimiter
	 * @return array<int|string, mixed>
	 */
	public static function loadEnvParameters(string $prefix = 'ORISAI', string $delimiter = '__'): array
	{
		if ($prefix !== '') {
			$prefix .= $delimiter;
		}

		$parameters = [];
		$prefixLength = strlen($prefix);
		foreach ($_SERVER + getenv() as $key => $value) {
			if ($prefix !== '' && strncmp((string) $key, $prefix, $prefixLength) !== 0) {
				continue;
			}

			$nonPrefixedKey = substr((string) $key, $prefixLength);
			self::mapParameters(
				$parameters,
				explode($delimiter, strtolower($nonPrefixedKey)),
				$value,
			);
		}

		return $parameters;
	}

	/**
	 * @param array<string, mixed> $array
	 * @param array<int, string>   $keys
	 * @param mixed                $value
	 * @return mixed
	 */
	private static function mapParameters(array &$array, array $keys, $value)
	{
		if (count($keys) <= 0) {
			return $value;
		}

		$key = array_shift($keys);

		if (!array_key_exists($key, $array)) {
			$array[$key] = [];
		}

		$array[$key] = self::mapParameters($array[$key], $keys, $value);

		return $array;
	}

	/**
	 * @param array<int|string, string> $values
	 * @param non-empty-string          $cookieName
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

	public static function isConsole(): bool
	{
		return PHP_SAPI === 'cli';
	}

	public static function isCookieDebug(DebugCookieStorage $storage): bool
	{
		$value = $_COOKIE[self::SidDebugCookie] ?? null;

		if ($value === null) {
			return false;
		}

		return $storage->has($value);
	}

}
