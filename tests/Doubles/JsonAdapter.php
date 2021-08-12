<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Doubles;

use Nette\DI\Config\Adapter;
use Nette\Utils\Json;
use Orisai\Exceptions\Logic\InvalidArgument;
use function file_get_contents;
use function is_array;

final class JsonAdapter implements Adapter
{

	/**
	 * @return array<mixed>
	 */
	public function load(string $file): array
	{
		$config = Json::decode(file_get_contents($file), Json::FORCE_ARRAY);

		if (!is_array($config)) {
			throw InvalidArgument::create()
				->withMessage("Config file $file has to return array.");
		}

		return $config;
	}

	/**
	 * @param array<mixed> $data
	 */
	public function dump(array $data): string
	{
		return Json::encode($data);
	}

}
