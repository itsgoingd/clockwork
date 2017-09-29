<?php namespace Clockwork\Support\Lumen;

use Clockwork\Support\Lumen\ClockworkSupport;

use Illuminate\Http\RedirectResponse;
use Laravel\Lumen\Routing\Controller as LumenController;

class Controller extends LumenController
{
	public $clockworkSupport;

	public function __construct(ClockworkSupport $clockworkSupport)
	{
		$this->clockworkSupport = $clockworkSupport;
	}

	public function getData($id = null, $direction = null, $count = null)
	{
		return $this->clockworkSupport->getData($id, $direction, $count);
	}

	public function webIndex()
	{
		return $this->clockworkSupport->getWebAsset('app.html');
	}

	public function webAsset($path)
	{
		return $this->clockworkSupport->getWebAsset("assets/{$path}");
	}

	public function webRedirect()
	{
		return new RedirectResponse('/__clockwork/app');
	}
}
