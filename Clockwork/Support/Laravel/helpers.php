<?php

if (! function_exists('clock')) {
	// Log a message to Clockwork, returns Clockwork instance when called with no arguments, first argument otherwise
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
