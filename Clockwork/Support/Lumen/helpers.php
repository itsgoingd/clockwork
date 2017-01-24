<?php

if (! function_exists('clock')) {
	// workaround so we can log null values with the helepr function
	if (! defined('CLOCKWORK_NULL')) {
		define('CLOCKWORK_NULL', sha1(time()));
	}

	/**
	 * Log a message to Clockwork, returns Clockwork instance when called with no arguments.
	 */
	function clock($message = CLOCKWORK_NULL)
	{
		if ($message === CLOCKWORK_NULL) {
			return app('clockwork');
		} else {
			foreach (func_get_args() as $arg) {
				app('clockwork')->debug($arg);
			}
		}
	}
}
