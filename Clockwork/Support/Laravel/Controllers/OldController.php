<?php namespace Clockwork\Support\Laravel\Controllers;

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
}
