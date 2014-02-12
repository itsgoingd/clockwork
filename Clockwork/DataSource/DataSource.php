<?php
namespace Clockwork\DataSource;

use Clockwork\Request\Request;

/**
 * Base data source class
 */
class DataSource implements DataSourceInterface
{
	/**
	 * Adds data to the request and returns it, custom implementation should be provided in child classes
	 */
	public function resolve(Request $request)
	{
		return $request;
	}

	/**
	 * Replaces unserializable items such as closures, resources and objects in an array with textual representation
	 */
	public function replaceUnserializable(array $data)
	{
		foreach ($data as &$item) {
			if ($item instanceof Closure)
				$item = 'anonymous function';
			elseif (is_resource($item))
				$item = 'resource';
			elseif (is_object($item))
				$item = 'instance of ' . get_class($item);
		}

		return $data;
	}

	/**
	 * Censors passwords in an array, identified by key containing "pass" substring
	 */
	public function removePasswords(array $data)
	{
		foreach ($data as $key => &$val)
			if (strpos($key, 'pass') !== false)
				$val = '*removed*';

		return $data;
	}
}
