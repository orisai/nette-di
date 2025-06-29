<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Boot\Parameters;

use Generator;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use OriNette\DI\Boot\Parameters\BaseUrl;
use PHPUnit\Framework\TestCase;

final class BaseUrlTest extends TestCase
{

	public function testNone(): void
	{
		$baseUrl = new BaseUrl();
		self::assertNull($baseUrl->get());
	}

	/**
	 * @dataProvider provide
	 */
	public function test(string $given, string $expected): void
	{
		$request = new Request(new UrlScript($given));
		$baseUrl = new BaseUrl($request);
		self::assertSame($expected, $baseUrl->get());
	}

	public function provide(): Generator
	{
		yield ['https://example.com', 'https://example.com'];
		yield ['https://example.com/', 'https://example.com'];
		yield ['https://example.com:8000/path', 'https://example.com:8000'];
	}

}
