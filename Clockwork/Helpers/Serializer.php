<?php namespace Clockwork\Helpers;

class Serializer
{
	// prepares the passed data for serialization with additional metadata up to specified levels of recursion
	public static function simplify($data, $levels = 3, $options = [])
	{
		if ($data instanceof \Closure) {
			return 'anonymous function';
		} elseif (is_array($data)) {
			if (! $levels) return $data;

			return array_map(function ($item) use ($levels) {
				return static::simplify($item, $levels - 1);
			}, $data);
		} elseif (is_object($data)) {
			if (isset($options['toString']) && $options['toString'] && method_exists($data, '__toString')) {
				return (string) $data;
			}

			if (! $levels) return $data;

			return array_merge(
				[ '__class__' => get_class($data) ],
				static::simplify((array) $data, $levels - 1)
			);
		} elseif (is_resource($data)) {
			return 'resource';
		}

		return $data;
	}
}
