<?php namespace Clockwork\Support\Laravel\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class CurrentController extends Controller
{
	protected $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	public function getData($id = null, $direction = null, $count = null)
	{
		return $this->app['clockwork.support']->getData($id, $direction, $count);
	}

	public function webIndex()
	{
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
