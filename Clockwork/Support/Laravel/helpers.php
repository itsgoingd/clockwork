<?php

if (! function_exists('clock')) {
	/**
	 * Log the message to Clockwork, returns Clockwork instance when called with no arguments.
	 */
	function clock($message = null)
	{
		if ($message === null) {
			return app('clockwork');
		} else {
			return app('clockwork')->debug($message);
		}
	}
}
