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
		if (! $this->clockworkSupport->isEnabled()) abort(404);

		$token = $this->clockwork->getAuthenticator()->attempt(
			$request->only([ 'username', 'password' ])
		);

		return new JsonResponse([ 'token' => $token ], $token ? 200 : 403);
	}

	public function getData($id = null, $direction = null, $count = null)
	{
		if (! $this->clockworkSupport->isEnabled()) abort(404);

		return $this->clockworkSupport->getData($id, $direction, $count);
	}

	public function getExtendedData($id = null)
	{
		if (! $this->clockworkSupport->isEnabled()) abort(404);

		return $this->clockworkSupport->getExtendedData($id);
	}

	public function webIndex(Request $request)
	{
		if (! $this->clockworkSupport->isEnabled()) abort(404);

		if ($this->clockworkSupport->isWebUsingDarkTheme() && ! $request->has('dark')) {
			return new RedirectResponse('/__clockwork/app?dark');
		}

		return $this->clockworkSupport->getWebAsset('app.html');
	}

	public function webAsset($path)
	{
		if (! $this->clockworkSupport->isEnabled()) abort(404);

		return $this->clockworkSupport->getWebAsset("assets/{$path}");
	}

	public function webRedirect()
	{
		if (! $this->clockworkSupport->isEnabled()) abort(404);

		return new RedirectResponse('/__clockwork/app');
	}
}
