<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;

// Data source interface, all data sources must implement this interface
interface DataSourceInterface
{
	// Adds collected data to the request and returns it
	public function resolve(Request $request);

	// Extends the request with an additional data, which is not required for normal use
	public function extend(Request $request);

	// Reset the data source to an empty state, clearing any collected data
	public function reset();
}
