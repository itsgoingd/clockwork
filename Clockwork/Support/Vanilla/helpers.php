<?php

use Clockwork\Support\Vanilla\Clockwork;

if (! function_exists('clock')) {
	/**
         * Log a message to Clockwork, returns Clockwork instance when called with no arguments, first argument otherwise
	 * @param mixed ...$arguments
 	 * @return ($arguments is empty ? \Clockwork\Clockwork : mixed)
         */
	function clock(...$arguments)
	{
		if (empty($arguments)) {
			return Clockwork::instance();
		}

		foreach ($arguments as $argument) {
			Clockwork::debug($argument);
		}

		return reset($arguments);
	}
}
