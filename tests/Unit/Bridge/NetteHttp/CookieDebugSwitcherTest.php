<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Bridge\NetteHttp;

use Nette\Http\Request;
use Nette\Http\UrlScript;
use OriNette\DI\Boot\Environment;
use OriNette\DI\Boot\FileDebugCookieStorage;
use OriNette\DI\Bridge\NetteHttp\CookieDebugSwitcher;
use OriNette\Http\Tester\TestResponse;
use Orisai\VFS\VFS;
use PHPUnit\Framework\TestCase;

final class CookieDebugSwitcherTest extends TestCase
{

	public function testSwitch(): void
	{
		$response = new TestResponse();
		$storage = new FileDebugCookieStorage(VFS::register() . '://dir/file.json');
		$request = new Request(new UrlScript('https://example.com'));
		$switcher = new CookieDebugSwitcher($request, $response, $storage);

		self::assertSame([], $response->getHeaders());

		$switcher->startDebug();

		$cookies = $response->getCookies();
		self::assertArrayHasKey('orisai-debug-sid', $cookies);

		$value = $cookies['orisai-debug-sid']['value'];
		self::assertNotSame('', $value);
		self::assertTrue($storage->has($value));

		// Value is available on the next request, we have to re-create
		$request = new Request(
			new UrlScript('https://example.com'),
			null,
			null,
			[
				Environment::SidDebugCookie => $value,
			],
		);
		$switcher = new CookieDebugSwitcher($request, $response, $storage);

		$switcher->stopDebug();

		$cookies = $response->getCookies();
		self::assertArrayHasKey('orisai-debug-sid', $cookies);

		$value = $cookies['orisai-debug-sid']['value'];
		self::assertSame('', $value);
		self::assertFalse($storage->has($value));
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSwitchEnv(): void
	{
		$response = new TestResponse();
		$storage = new FileDebugCookieStorage(VFS::register() . '://dir/file.json');
		$request = new Request(new UrlScript('https://example.com'));
		$switcher = new CookieDebugSwitcher($request, $response, $storage);

		self::assertFalse($switcher->isDebug());
		self::assertFalse(Environment::isCookieDebug($storage));

		$switcher->startDebug();

		$cookies = $response->getCookies();
		self::assertArrayHasKey('orisai-debug-sid', $cookies);

		$value = $cookies['orisai-debug-sid']['value'];
		$_COOKIE[Environment::SidDebugCookie] = $value;

		self::assertTrue($switcher->isDebug());
		self::assertTrue(Environment::isCookieDebug($storage));

		$switcher->stopDebug();
		unset($_COOKIE[Environment::SidDebugCookie]);

		self::assertFalse($switcher->isDebug());
		self::assertFalse(Environment::isCookieDebug($storage));
	}

	public function testStopNoCookie(): void
	{
		$response = new TestResponse();
		$storage = new FileDebugCookieStorage(VFS::register() . '://dir/file.json');
		$request = new Request(new UrlScript('https://example.com'));
		$switcher = new CookieDebugSwitcher($request, $response, $storage);

		self::assertSame([], $response->getHeaders());
		$switcher->stopDebug();
		self::assertSame([], $response->getHeaders());
	}

}
