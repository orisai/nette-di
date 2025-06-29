<?php declare(strict_types = 1);

namespace OriNette\DI\Boot\Parameters;

use Nette\Http\IRequest;
use function rtrim;

/**
 * @internal
 */
final class BaseUrl
{

	private ?IRequest $request;

	public function __construct(?IRequest $request = null)
	{
		$this->request = $request;
	}

	public function get(): ?string
	{
		return $this->request === null
			? null
			: rtrim($this->request->getUrl()->getBaseUrl(), '/');
	}

}
