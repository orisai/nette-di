<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Boot;

use OriNette\DI\Boot\FileDebugCookieStorage;
use Orisai\VFS\VFS;
use PHPUnit\Framework\TestCase;

final class FileDebugCookieStorageTest extends TestCase
{

	public function test(): void
	{
		$storage = new FileDebugCookieStorage(VFS::register() . '://dir/file.json');

		$storage->add('1');
		self::assertTrue($storage->has('1'));
		self::assertFalse($storage->has('2'));

		$storage->add('2');
		self::assertTrue($storage->has('1'));
		self::assertTrue($storage->has('2'));

		$storage->remove('1');
		self::assertFalse($storage->has('1'));
		self::assertTrue($storage->has('2'));
	}

}
