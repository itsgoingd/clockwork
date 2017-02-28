<?php namespace Clockwork\DataSource;

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
		return array_map(function ($item) {
			if ($item instanceof \Closure) {
				return 'anonymous function';
			} elseif (is_resource($item)) {
				return 'resource';
			} elseif (is_object($item)) {
				return 'instance of ' . get_class($item);
			} else {
				return $item;
			}
		}, $data);
	}

	/**
	 * Censors passwords in an array, identified by key containing "pass" substring
	 */
	public function removePasswords(array $data)
	{
		$keys = array_keys($data);
		$values = array_map(function ($value, $key) {
			return strpos($key, 'pass') !== false ? '*removed*' : $value;
		}, $data, $keys);

		return array_combine($keys, $values);
	}

	/**
	 * Attempts to serialize a variable of unspecified type
	 */
	protected function serialize($message)
	{
		if (is_object($message)) {
			if (method_exists($message, '__toString')) {
				return (string) $message;
			} elseif (method_exists($message, 'toArray')) {
				return json_encode($message->toArray());
			} else {
				return json_encode((array) $message);
			}
		} elseif (is_array($message)) {
			return json_encode($message);
		}

		return $message;
	}
}
