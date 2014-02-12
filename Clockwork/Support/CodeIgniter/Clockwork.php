<?php

/**
 * The Clockwork router for CodeIgniter. Must be copied into the controller 
 * directory in your application. Add the following route to use the default URL
 * the extension uses:
 * 
 *     $route['__clockwork/(.*)'] = 'clockwork/$1';
 */
class Clockwork extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		Clockwork\Support\CodeIgniter\Hook::disable();
	}
	
	public function index($id = null, $last = null)
	{
		header('Content-Type: application/json');
		echo Clockwork\Support\CodeIgniter\Hook::getStorage()->retrieveAsJson($id, $last);
	}
	
	public function _remap($func, $args)
	{
		if (count($args) > 0) {
			return $this->index($func, $args[0]);
		} else {
			return $this->index($func);
		}
	}
}
