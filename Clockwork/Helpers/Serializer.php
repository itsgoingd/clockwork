<?php namespace Clockwork\Helpers;

class Serializer
{
	// prepares the passed data for serialization with additional metadata up to specified levels of recursion
	public static function simplify($data, $levels = 3, $options = [])
	{
		if ($data instanceof \Closure) {
			return 'anonymous function';
		} elseif (is_array($data)) {
			$cleanData = [];
			foreach ($data as $key => $item) {
				$key = preg_match('/^\x00(?:.*?)\x00(.+)/', $key, $matches) ? $matches[1] : $key;
				$cleanData[$key] = $item;
			}

			if (!$levels) return $cleanData;

			return array_map(function ($item) use ($levels) {
				return static::simplify($item, $levels - 1);
			}, $cleanData);
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
