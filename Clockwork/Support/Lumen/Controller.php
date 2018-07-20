<?php namespace Clockwork\Support\Lumen;

use Clockwork\Clockwork;
use Clockwork\Support\Lumen\ClockworkSupport;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as LumenController;

class Controller extends LumenController
{
	public $clockwork;
	public $clockworkSupport;

	public function __construct(Clockwork $clockwork, ClockworkSupport $clockworkSupport)
	{
		$this->clockwork = $clockwork;
		$this->clockworkSupport = $clockworkSupport;
	}

	public function authenticate(Request $request)
	{
		$token = $this->clockwork->getAuthenticator()->attempt(
			$request->only([ 'username', 'password' ])
		);

		return new JsonResponse([ 'token' => $token ], $token ? 200 : 403);
	}

	public function getData($id = null, $direction = null, $count = null)
	{
		return $this->clockworkSupport->getData($id, $direction, $count);
	}

	public function getExtendedData($id = null)
	{
		return $this->app['clockwork.support']->getExtendedData($id);
	}

	public function webIndex(Request $request)
	{
		if ($this->clockworkSupport->isWebUsingDarkTheme() && ! $request->has('dark')) {
			return new RedirectResponse('/__clockwork/app?dark');
		}

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
