<?php declare(strict_types = 1);

namespace OriNette\DI\Boot;

use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use function array_keys;
use function file_get_contents;
use function in_array;

final class FileDebugCookieStorage implements DebugCookieStorage
{

	private string $file;

	public function __construct(string $file)
	{
		$this->file = $file;
	}

	public function add(string $value): void
	{
		$values = $this->read();
		$values[] = $value;

		$this->write($values);
	}

	public function remove(string $value): void
	{
		$values = $this->read();

		foreach (array_keys($values, $value, true) as $key) {
			unset($values[$key]);
		}

		$this->write($values);
	}

	public function has(string $value): bool
	{
		return in_array($value, $this->read(), true);
	}

	/**
	 * @return array<int|string, mixed>
	 */
	private function read(): array
	{
		$content = @file_get_contents($this->file);

		return $content !== false
			? Json::decode($content, Json::FORCE_ARRAY)
			: [];
	}

	/**
	 * @param array<int|string, mixed> $values
	 */
	private function write(array $values): void
	{
		FileSystem::write($this->file, Json::encode($values, Json::PRETTY));
	}

}
