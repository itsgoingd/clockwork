<?php namespace Clockwork\Support\Lumen;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Lumen\Routing\Controller as LumenController;

class Controller extends LumenController {

	public $app;

	public function __construct(Application $app) {
		$this->app = $app;
	}

	public function getData($id = null, $last = null) {
		return $this->app['clockwork.support']->getData($id, $last);
	}
}
