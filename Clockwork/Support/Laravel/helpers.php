<?php

if (! function_exists('clock')) {
	/**
         * Log a message to Clockwork, returns Clockwork instance when called with no arguments, first argument otherwise
	 * @param mixed ...$arguments
 	 * @return ($arguments is empty ? \Clockwork\Clockwork : mixed)
         */
	function clock(...$arguments)
	{
		if (empty($arguments)) {
			return app('clockwork');
		}

		foreach ($arguments as $argument) {
			app('clockwork')->debug($argument);
		}

		return reset($arguments);
	}
}
