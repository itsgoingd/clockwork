<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;

/**
 * Data source interface, all data sources must implement this interface
 */
interface DataSourceInterface
{
	/**
	 * Adds data to the request and returns it
	 */
	public function resolve(Request $request);

	// Extends the request with additional data when being shown in the Clockwork app
	public function extend(Request $request);

	// Reset the data source to an empty state, clearing any collected data
	public function reset();
}
