<?php

use Clockwork\Support\Vanilla\Clockwork;

if (! function_exists('clock')) {
	/**
	 * Log a message to Clockwork, returns Clockwork instance when called with no arguments.
	 */
	function clock()
	{
		$arguments = func_get_args();

		if (empty($arguments)) {
			return Clockwork::instance();
		}

		foreach ($arguments as $argument) {
			Clockwork::debug($argument);
		}

		return reset($arguments);
	}
}
