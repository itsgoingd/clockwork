<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;

// Data source for Xdebug, provides profiling data
class XdebugDataSource extends DataSource
{
	// Adds profiling data path to the request
	public function resolve(Request $request)
	{
		$request->xdebug = [ 'profile' => xdebug_get_profiler_filename() ];

		return $request;
	}

	// Extends the request with full profiling data
	public function extend(Request $request)
	{
		$profile = isset($request->xdebug['profile']) ? $request->xdebug['profile'] : null;

		if ($profile && ! preg_match('/\.php$/', $profile) && is_readable($profile)) {
			$request->xdebug['profileData'] = file_get_contents($profile);

			if (preg_match('/\.gz$/', $profile)) {
				$request->xdebug['profileData'] = gzdecode($request->xdebug['profileData']);
			}
		}

		return $request;
	}
}
