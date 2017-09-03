<?php

if (! function_exists('clock')) {
	/**
	 * Log a message to Clockwork, returns Clockwork instance when called with no arguments.
	 */
	function clock()
	{
		$arguments = func_get_args();

		if (empty($arguments)) {
			return app('clockwork');
		}

		foreach ($arguments as $argument) {
			app('clockwork')->debug($argument);
		}
	}
}
