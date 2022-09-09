<?php declare(strict_types = 1);

namespace OriNette\DI\Boot;

interface DebugCookieStorage
{

	public function add(string $value): void;

	public function remove(string $value): void;

	public function has(string $value): bool;

}
