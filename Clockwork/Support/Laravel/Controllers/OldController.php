<?php namespace Clockwork\Support\Laravel\Controllers;

use Illuminate\Routing\Controllers\Controller;

class OldController extends Controller {

	public $app;

	public function __construct() {
		$this->app = app();
	}

	public function getData($id = null, $last = null) {
		return $this->app['clockwork.support']->getData($id, $last);
	}
}
