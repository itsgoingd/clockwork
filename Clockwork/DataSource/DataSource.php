<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;

// Base data source class
class DataSource implements DataSourceInterface
{
	// Array of filter functions
	protected $filters = [];

	// Adds collected data to the request and returns it, to be implemented by extending classes
	public function resolve(Request $request)
	{
		return $request;
	}

	// Extends the request with an additional data, which is not required for normal use
	public function extend(Request $request)
	{
		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
	}

	// Register a new filter
	public function addFilter(\Closure $filter, $type = 'default')
	{
		$this->filters[$type] = array_merge($this->filters[$type] ?? [], [ $filter ]);

		return $this;
	}

	// Clear all registered filters
	public function clearFilters()
	{
		$this->filters = [];

		return $this;
	}

	// Returns boolean whether the filterable passes all registered filters
	protected function passesFilters($args, $type = 'default')
	{
		$filters = $this->filters[$type] ?? [];

		foreach ($filters as $filter) {
			if (! $filter(...$args)) return false;
		}

		return true;
	}

	// Censors passwords in an array, identified by key containing "pass" substring
	public function removePasswords(array $data)
	{
		$keys = array_keys($data);
		$values = array_map(function ($value, $key) {
			return strpos($key, 'pass') !== false ? '*removed*' : $value;
		}, $data, $keys);

		return array_combine($keys, $values);
	}
}
