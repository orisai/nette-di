<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Bridge\NetteHttp;

use DateTimeInterface;
use Nette\Http\Helpers;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Utils\DateTime;
use OriNette\DI\Boot\Environment;
use OriNette\DI\Boot\FileDebugCookieStorage;
use OriNette\DI\Bridge\NetteHttp\CookieDebugSwitcher;
use Orisai\VFS\VFS;
use PHPUnit\Framework\TestCase;
use function implode;
use function strlen;
use function substr;
use function time;

final class CookieDebugSwitcherTest extends TestCase
{

	private IResponse $response;

	protected function setUp(): void
	{
		parent::setUp();

		/**
		 * This is far from being compatible with default Response, don't try to use it elsewhere
		 */
		$this->response = new class implements IResponse {

			private int $code = self::S200_OK;

			/** @var array<string, array<string>> */
			private array $headers = [];

			public function setCode(int $code, ?string $reason = null): self
			{
				$this->code = $code;

				return $this;
			}

			public function getCode(): int
			{
				return $this->code;
			}

			public function setHeader(string $name, ?string $value): self
			{
				$this->headers[$name] = [$value];

				return $this;
			}

			public function addHeader(string $name, string $value): self
			{
				$this->headers[$name][] = $value;

				return $this;
			}

			public function setContentType(string $type, ?string $charset = null): self
			{
				$this->setHeader(
					'Content-Type',
					$type . ($charset !== null ? '; charset=' . $charset : ''),
				);

				return $this;
			}

			public function redirect(string $url, int $code = self::S302_FOUND): void
			{
				$this->setCode($code);
				$this->setHeader('Location', $url);
			}

			public function setExpiration(?string $expire): self
			{
				$this->setHeader('Pragma', null);
				if ($expire === null) { // no cache
					$this->setHeader('Cache-Control', 's-maxage=0, max-age=0, must-revalidate');
					$this->setHeader('Expires', 'Mon, 23 Jan 1978 10:00:00 GMT');

					return $this;
				}

				$time = DateTime::from($expire);
				$this->setHeader('Cache-Control', 'max-age=' . ($time->format('U') - time()));
				$this->setHeader('Expires', Helpers::formatDate($time));

				return $this;
			}

			public function isSent(): bool
			{
				return false;
			}

			public function getHeader(string $header): ?string
			{
				if (!isset($this->headers[$header])) {
					return null;
				}

				return $header . ': ' . implode(',', $this->headers[$header]);
			}

			public function deleteHeader(string $name): self
			{
				unset($this->headers[$name]);

				return $this;
			}

			public function getHeaders(): array
			{
				return $this->headers;
			}

			/**
			 * @param string|int|DateTimeInterface $expire
			 */
			public function setCookie(
				string $name,
				string $value,
				$expire,
				?string $path = null,
				?string $domain = null,
				?bool $secure = null,
				?bool $httpOnly = null
			): self
			{
				// This is definitely wrong and incomplete
				$this->setHeader('Set-Cookie', "$name=$value");

				return $this;
			}

			public function deleteCookie(
				string $name,
				?string $path = null,
				?string $domain = null,
				?bool $secure = null
			): void
			{
				// This is definitely wrong and incomplete
				$this->setHeader('Set-Cookie', "$name=");
			}

		};
	}

	public function testSwitch(): void
	{
		$storage = new FileDebugCookieStorage(VFS::register() . '://dir/file.json');
		$request = new Request(new UrlScript('https://example.com'));
		$switcher = new CookieDebugSwitcher($request, $this->response, $storage);

		self::assertSame([], $this->response->getHeaders());

		$switcher->startDebug();

		$keyValue = $this->response->getHeaders()['Set-Cookie'][0];
		self::assertStringStartsWith('orisai-debug-sid=', $keyValue);

		$value = substr($keyValue, strlen('orisai-debug-sid='));
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
		$switcher = new CookieDebugSwitcher($request, $this->response, $storage);

		$switcher->stopDebug();
		self::assertSame('orisai-debug-sid=', $this->response->getHeaders()['Set-Cookie'][0]);
		self::assertFalse($storage->has($value));
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSwitchEnv(): void
	{
		$storage = new FileDebugCookieStorage(VFS::register() . '://dir/file.json');
		$request = new Request(new UrlScript('https://example.com'));
		$switcher = new CookieDebugSwitcher($request, $this->response, $storage);

		self::assertFalse($switcher->isDebug());
		self::assertFalse(Environment::isCookieDebug($storage));

		$switcher->startDebug();
		$value = substr($this->response->getHeaders()['Set-Cookie'][0], strlen('orisai-debug-sid='));
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
		$storage = new FileDebugCookieStorage(VFS::register() . '://dir/file.json');
		$request = new Request(new UrlScript('https://example.com'));
		$switcher = new CookieDebugSwitcher($request, $this->response, $storage);

		self::assertSame([], $this->response->getHeaders());
		$switcher->stopDebug();
		self::assertSame([], $this->response->getHeaders());
	}

}
