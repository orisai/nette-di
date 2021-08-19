<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Boot;

use OriNette\DI\Boot\Environment;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
final class EnvironmentTest extends TestCase
{

	public function testEnvDebugMode(): void
	{
		unset($_SERVER['ORISAI_DEBUG']);
		self::assertFalse(Environment::isEnvDebugMode());

		$_SERVER['ORISAI_DEBUG'] = 'anything';
		self::assertFalse(Environment::isEnvDebugMode());

		$_SERVER['ORISAI_DEBUG'] = '1';
		self::assertTrue(Environment::isEnvDebugMode());

		$_SERVER['ORISAI_DEBUG'] = 'true';
		self::assertTrue(Environment::isEnvDebugMode());

		$_SERVER['ORISAI_DEBUG'] = 'TRUE';
		self::assertTrue(Environment::isEnvDebugMode());

		$_SERVER['ORISAI_DEBUG'] = 'tRuE';
		self::assertTrue(Environment::isEnvDebugMode());
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
		];

		self::assertSame(
			[
				'key' => 'key',
				'another' => [
					'key' => 'another.key',
				],
			],
			Environment::loadEnvParameters(''),
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

}
