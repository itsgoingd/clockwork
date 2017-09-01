<?php namespace Clockwork\Support\Laravel\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\Controller;

class OldController extends Controller
{
	protected $app;

	public function __construct()
	{
		$this->app = app();
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
