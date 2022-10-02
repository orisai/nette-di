<?php declare(strict_types = 1);

namespace OriNette\DI\Bridge\NetteHttp;

use Nette\Http\IRequest;
use Nette\Http\IResponse;
use OriNette\DI\Boot\DebugCookieStorage;
use OriNette\DI\Boot\Environment;
use function bin2hex;
use function random_bytes;

final class CookieDebugSwitcher
{

	private IRequest $request;

	private IResponse $response;

	private DebugCookieStorage $storage;

	public function __construct(IRequest $request, IResponse $response, DebugCookieStorage $storage)
	{
		$this->request = $request;
		$this->response = $response;
		$this->storage = $storage;
	}

	public function isDebug(): bool
	{
		return Environment::isCookieDebug($this->storage);
	}

	public function startDebug(): void
	{
		$value = bin2hex(random_bytes(128));

		$this->storage->add($value);

		$this->response->setCookie(
			Environment::SidDebugCookie,
			$value,
			2_147_483_647,
		);
	}

	public function stopDebug(): void
	{
		$value = $this->request->getCookie(Environment::SidDebugCookie);

		if ($value === null) {
			return;
		}

		$this->storage->remove($value);

		$this->response->deleteCookie(Environment::SidDebugCookie);
	}

}
