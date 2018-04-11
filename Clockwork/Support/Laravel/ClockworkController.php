<?php namespace Clockwork\Support\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class ClockworkController extends Controller
{
	protected $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	public function authenticate()
	{
		$token = $this->app['clockwork']->getAuthenticator()->attempt(
			$this->app['request']->only([ 'username', 'password' ])
		);

		return new JsonResponse([ 'token' => $token ], $token ? 200 : 403);
	}

	public function getData($id = null, $direction = null, $count = null)
	{
		return $this->app['clockwork.support']->getData($id, $direction, $count);
	}

	public function webIndex()
	{
		if ($this->app['clockwork.support']->isWebUsingDarkTheme() && ! $this->app['request']->has('dark')) {
			return new RedirectResponse('/__clockwork/app?dark');
		}

		return $this->app['clockwork.support']->getWebAsset('app.html');
	}

	public function webAsset($path)
	{
		return $this->app['clockwork.support']->getWebAsset("assets/{$path}");
	}

	public function webRedirect()
	{
		return new RedirectResponse('/__clockwork/app');
	}
}
