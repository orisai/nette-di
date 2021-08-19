<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Boot;

use OriNette\DI\Boot\CookieGetter;
use PHPUnit\Framework\TestCase;

final class CookieGetterTest extends TestCase
{

	/**
	 * @runInSeparateProcess
	 */
	public function testFromEnv(): void
	{
		$_SERVER = [];

		self::assertSame(
			[],
			CookieGetter::fromEnv(),
		);

		$_SERVER['DEBUG_COOKIE_VALUES'] = 'foo,bar,baz';
		self::assertSame(
			['foo', 'bar', 'baz'],
			CookieGetter::fromEnv(),
		);

		self::assertSame(
			[],
			CookieGetter::fromEnv('TEST', ';'),
		);

		$_SERVER['TEST'] = 'foo;bar;baz';
		self::assertSame(
			['foo', 'bar', 'baz'],
			CookieGetter::fromEnv('TEST', ';'),
		);
	}

}
