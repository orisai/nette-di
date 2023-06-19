<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Boot;

use OriNette\DI\Boot\Environment;
use OriNette\DI\Boot\FileDebugCookieStorage;
use Orisai\VFS\VFS;
use PHPUnit\Framework\TestCase;
use function putenv;
use const PHP_SAPI;

/**
 * @runTestsInSeparateProcesses
 */
final class EnvironmentTest extends TestCase
{

	public function testEnvDebugMode(): void
	{
		unset($_SERVER['ORISAI_DEBUG']);
		putenv('ORISAI_DEBUG=false');
		self::assertFalse(Environment::isEnvDebug());
		self::assertFalse(Environment::isEnvDebugMode());

		$_SERVER['ORISAI_DEBUG'] = 'anything';
		self::assertFalse(Environment::isEnvDebug());

		$_SERVER['ORISAI_DEBUG'] = '1';
		self::assertTrue(Environment::isEnvDebug());

		$_SERVER['ORISAI_DEBUG'] = 'true';
		self::assertTrue(Environment::isEnvDebug());

		$_SERVER['ORISAI_DEBUG'] = 'TRUE';
		self::assertTrue(Environment::isEnvDebug());

		$_SERVER['ORISAI_DEBUG'] = 'tRuE';
		self::assertTrue(Environment::isEnvDebug());
	}

	public function testEnvDebugModeGetEnvFallback(): void
	{
		unset($_SERVER['ORISAI_DEBUG']);
		putenv('ORISAI_DEBUG=false');
		self::assertFalse(Environment::isEnvDebug());

		putenv('ORISAI_DEBUG=true');
		self::assertTrue(Environment::isEnvDebug());

		$_SERVER['ORISAI_DEBUG'] = 'false';
		self::assertFalse(Environment::isEnvDebug());
	}

	public function testEnvParameters(): void
	{
		$_SERVER = [
			'ORISAI__' => 'empty',
			'ORISAI__PARAMETER' => 'parameter',
			'ORISAI__SINGLE_UNDERSCORE' => 'single_underscore',
			'ORISAI__UPPER__lower__MiXeD' => 'upper.lower.mixed',
			'ORISAI__UPPER__another__parameter' => 'upper.another.parameter',

			'PREFIX:' => 'empty',
			'PREFIX:PARAMETER' => 'parameter',
			'PREFIX:UPPER:lower:MiXeD' => 'upper.lower.mixed',

			'ANOTHER_PREFIX_' => 'empty',
			'ANOTHER_PREFIX_PARAMETER' => 'parameter',
			'ANOTHER_PREFIX_UPPER_lower_MiXeD' => 'upper.lower.mixed',

			'IGNORED' => 'ignored',
			0 => 'numeric key',
		];

		self::assertSame(
			[
				'' => 'empty',
				'parameter' => 'parameter',
				'single_underscore' => 'single_underscore',
				'upper' => [
					'lower' => [
						'mixed' => 'upper.lower.mixed',
					],
					'another' => [
						'parameter' => 'upper.another.parameter',
					],
				],
			],
			Environment::loadEnvParameters(),
		);

		self::assertSame(
			[
				'' => 'empty',
				'parameter' => 'parameter',
				'upper' => [
					'lower' => [
						'mixed' => 'upper.lower.mixed',
					],
				],
			],
			Environment::loadEnvParameters('PREFIX', ':'),
		);

		self::assertSame(
			[
				'' => 'empty',
				'parameter' => 'parameter',
				'upper' => [
					'lower' => [
						'mixed' => 'upper.lower.mixed',
					],
				],
			],
			Environment::loadEnvParameters('ANOTHER_PREFIX', '_'),
		);

		self::assertSame([], Environment::loadEnvParameters('IGNORED'));
	}

	public function testEnvParametersWithNoPrefix(): void
	{
		$_SERVER = [
			'KEY' => 'key',
			'ANOTHER__KEY' => 'another.key',
			0 => 'numeric key',
		];

		$parameters = Environment::loadEnvParameters('');

		self::assertSame($parameters['key'], 'key');
		self::assertSame($parameters['another'], ['key' => 'another.key']);
		self::assertSame($parameters[0], 'numeric key');
	}

	public function testEnvParametersGetEnvFallback(): void
	{
		$_SERVER = [];
		putenv('ORISAI__B=getenv');
		putenv('ORISAI__C=getenv');

		self::assertSame(
			[
				'b' => 'getenv',
				'c' => 'getenv',
			],
			Environment::loadEnvParameters(),
		);

		$_SERVER = [
			'ORISAI__A' => 'server',
			'ORISAI__B' => 'server',
		];

		self::assertSame(
			[
				'a' => 'server',
				'b' => 'server',
				'c' => 'getenv',
			],
			Environment::loadEnvParameters(),
		);
	}

	public function testLocalhost(): void
	{
		unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_FORWARDED']);

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		self::assertTrue(Environment::isLocalhost());

		$_SERVER['REMOTE_ADDR'] = '::1';
		self::assertTrue(Environment::isLocalhost());

		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';
		self::assertFalse(Environment::isLocalhost());
	}

	public function testLocalhostWithProxy(): void
	{
		unset($_SERVER['HTTP_FORWARDED']);
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'anything';

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		self::assertFalse(Environment::isLocalhost());

		$_SERVER['REMOTE_ADDR'] = '::1';
		self::assertFalse(Environment::isLocalhost());

		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';
		self::assertFalse(Environment::isLocalhost());
	}

	public function testLocalhostWithNonStandardProxy(): void
	{
		unset($_SERVER['HTTP_X_FORWARDED_FOR']);
		$_SERVER['HTTP_FORWARDED'] = 'anything';

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		self::assertFalse(Environment::isLocalhost());

		$_SERVER['REMOTE_ADDR'] = '::1';
		self::assertFalse(Environment::isLocalhost());

		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';
		self::assertFalse(Environment::isLocalhost());
	}

	public function testLocalhostUname(): void
	{
		unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_FORWARDED'], $_SERVER['REMOTE_ADDR']);

		self::assertFalse(Environment::isLocalhost());
	}

	public function testDebugCookie(): void
	{
		self::assertFalse(Environment::hasCookie([]));

		$_COOKIE['orisai-debug'] = 'foo';
		self::assertFalse(Environment::hasCookie([]));

		self::assertTrue(Environment::hasCookie([
			'foo',
		]));

		self::assertTrue(Environment::hasCookie([
			'foo',
			'bar',
		]));

		self::assertFalse(Environment::hasCookie([
			'foo',
		], 'another-debug'));

		$_COOKIE['another-debug'] = 'foo';
		self::assertTrue(Environment::hasCookie([
			'foo',
		], 'another-debug'));
	}

	public function testConsole(): void
	{
		self::assertSame(PHP_SAPI === 'cli', Environment::isConsole());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCookieDebugSession(): void
	{
		$cookieStorage = new FileDebugCookieStorage(VFS::register() . '://dir/file.json');

		self::assertFalse(Environment::isCookieDebug($cookieStorage));

		$_COOKIE[Environment::SidDebugCookie] = '1';
		self::assertFalse(Environment::isCookieDebug($cookieStorage));

		$cookieStorage->add('1');
		self::assertTrue(Environment::isCookieDebug($cookieStorage));
	}

}
