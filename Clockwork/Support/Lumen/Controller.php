<?php namespace Clockwork\Support\Lumen;

use Clockwork\Clockwork;
use Clockwork\Support\Lumen\ClockworkSupport;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as LumenController;
use Laravel\Telescope\Telescope;

// Clockwork api and app controller
class Controller extends LumenController
{
	// Clockwork and support instances
	public $clockwork;
	public $clockworkSupport;

	public function __construct(Clockwork $clockwork, ClockworkSupport $clockworkSupport)
	{
		$this->clockwork = $clockwork;
		$this->clockworkSupport = $clockworkSupport;
	}

	// Authantication endpoint
	public function authenticate(Request $request)
	{
		$this->ensureClockworkIsEnabled();

		$token = $this->clockwork->authenticator()->attempt(
			$request->only([ 'username', 'password' ])
		);

		return new JsonResponse([ 'token' => $token ], $token ? 200 : 403);
	}

	// Metadata retrieving endpoint
	public function getData(Request $request, $id = null, $direction = null, $count = null)
	{
		$this->ensureClockworkIsEnabled();

		return $this->clockworkSupport->getData(
			$id, $direction, $count, $request->only([ 'only', 'except' ])
		);
	}

	// Extended metadata retrieving endpoint
	public function getExtendedData(Request $request, $id = null)
	{
		$this->ensureClockworkIsEnabled();

		return $this->clockworkSupport->getExtendedData(
			$id, $request->only([ 'only', 'except' ])
		);
	}

	// Metadata updating endpoint
	public function updateData(Request $request, $id = null)
	{
		$this->ensureClockworkIsEnabled();

		return $this->clockworkSupport->updateData($id, $request->json()->all());
	}

	// App index
	public function webIndex(Request $request)
	{
		$this->ensureClockworkIsEnabled();

		return $this->clockworkSupport->getWebAsset('index.html');
	}

	// App assets serving
	public function webAsset($path)
	{
		$this->ensureClockworkIsEnabled();

		return $this->clockworkSupport->getWebAsset($path);
	}

	// App redirect (/clockwork -> /clockwork/app)
	public function webRedirect(Request $request)
	{
		$this->ensureClockworkIsEnabled();

		return new RedirectResponse($request->path() . '/app');
	}

	// Ensure Clockwork is still enabled at this point and stop Telescope recording if present
	protected function ensureClockworkIsEnabled()
	{
		if (class_exists(Telescope::class)) Telescope::stopRecording();

		if (! $this->clockworkSupport->isEnabled()) abort(404);
	}
}
