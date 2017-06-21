<?php namespace Clockwork\Support\Laravel\Controllers;

use Illuminate\Foundation\Application;
use Illuminate\Routing\Controller;

class LegacyController extends Controller
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
}
