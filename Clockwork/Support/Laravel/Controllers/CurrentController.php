<?php namespace Clockwork\Support\Laravel\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Controller;

class CurrentController extends Controller {

	public $app;

	public function __construct(Application $app) {
		$this->app = $app;
	}

	public function getData($id = null, $last = null) {
		return $this->app['clockwork.support']->getData($id, $last);
	}
}
