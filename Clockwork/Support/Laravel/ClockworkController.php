<?php namespace Clockwork\Support\Laravel;

use Clockwork\Clockwork;
use Clockwork\Support\Laravel\ClockworkSupport;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Telescope\Telescope;

// Clockwork api and app controller
class ClockworkController extends Controller
{
	// Authantication endpoint
	public function authenticate(Clockwork $clockwork, ClockworkSupport $clockworkSupport, Request $request)
	{
		$this->ensureClockworkIsEnabled($clockworkSupport);

		$token = $clockwork->authenticator()->attempt(
			$request->only([ 'username', 'password' ])
		);

		return new JsonResponse([ 'token' => $token ], $token ? 200 : 403);
	}

	// Metadata retrieving endpoint
	public function getData(ClockworkSupport $clockworkSupport, Request $request, $id = null, $direction = null, $count = null)
	{
		$this->ensureClockworkIsEnabled($clockworkSupport);

		return $clockworkSupport->getData(
			$id, $direction, $count, $request->only([ 'only', 'except' ])
		);
	}

	// Extended metadata retrieving endpoint
	public function getExtendedData(ClockworkSupport $clockworkSupport, Request $request, $id = null)
	{
		$this->ensureClockworkIsEnabled($clockworkSupport);

		return $clockworkSupport->getExtendedData(
			$id, $request->only([ 'only', 'except' ])
		);
	}

	// Metadata updating endpoint
	public function updateData(ClockworkSupport $clockworkSupport, Request $request, $id = null)
	{
		$this->ensureClockworkIsEnabled($clockworkSupport);

		return $clockworkSupport->updateData($id, $request->json()->all());
	}

	// App index
	public function webIndex(ClockworkSupport $clockworkSupport)
	{
		$this->ensureClockworkIsEnabled($clockworkSupport);

		return $clockworkSupport->getWebAsset('index.html');
	}

	// App assets serving
	public function webAsset(ClockworkSupport $clockworkSupport, $path)
	{
		$this->ensureClockworkIsEnabled($clockworkSupport);

		return $clockworkSupport->getWebAsset($path);
	}

	// App redirect (/clockwork -> /clockwork/app)
	public function webRedirect(ClockworkSupport $clockworkSupport, Request $request)
	{
		$this->ensureClockworkIsEnabled($clockworkSupport);

		return new RedirectResponse($request->path() . '/app');
	}

	// Ensure Clockwork is still enabled at this point and stop Telescope recording if present
	protected function ensureClockworkIsEnabled(ClockworkSupport $clockworkSupport)
	{
		if (class_exists(Telescope::class)) Telescope::stopRecording();

		if (! $clockworkSupport->isEnabled()) abort(404);
	}
}
