<?php namespace Clockwork\Helpers;

// Prepares various types of data for serialization
class Serializer
{
	// Serialized objects cache by object hash
	protected $cache = [];

	// Options for the current instance
	protected $options = [];

	// Default options for new instances
	protected static $defaults = [
		'blackbox' => [
			\Illuminate\Container\Container::class,
			\Illuminate\Foundation\Application::class,
			\Laravel\Lumen\Application::class
		],
		'limit' => 10,
		'toArray' => false,
		'toString' => false,
		'debugInfo' => true,
		'jsonSerialize' => false,
		'traces' => true,
		'tracesFilter' => null,
		'tracesSkip' => null,
		'tracesLimit' => null
	];

	// Create a new instance optionally with options overriding defaults
	public function __construct(array $options = [])
	{
		$this->options = $options + static::$defaults;
	}

	// Set default options for all new instances
	public static function defaults(array $defaults)
	{
		static::$defaults = $defaults + static::$defaults;
	}

	// Prepares the passed data to be ready for serialization, takes any kind of data to normalize as the first
	// argument, other arguments are used internally in recursion
	public function normalize($data, $context = null, $limit = null)
	{
		if ($context === null) $context = [ 'references' => [] ];
		if ($limit === null) $limit = $this->options['limit'];

		if (is_array($data)) {
			if ($limit === 0) return [ '__type__' => 'array', '__omitted__' => 'limit' ];

			return [ '__type__' => 'array' ] + $this->normalizeEach($data, $context, $limit - 1);
		} elseif (is_object($data)) {
			if ($data instanceof \Closure) return [ '__type__' => 'anonymous function' ];

			$className = get_class($data);
			$objectHash = spl_object_hash($data);

			if ($limit === 0) return [ '__class__' => $className, '__omitted__' => 'limit' ];

			if (isset($context['references'][$objectHash])) return [ '__type__' => 'recursion' ];

			$context['references'][$objectHash] = true;

			if (isset($this->cache[$objectHash])) return $this->cache[$objectHash];

			if ($this->options['blackbox'] && in_array($className, $this->options['blackbox'])) {
				return $this->cache[$objectHash] = [ '__class__' => $className, '__omitted__' => 'blackbox' ];
			} elseif ($this->options['toString'] && method_exists($data, '__toString')) {
				return $this->cache[$objectHash] = (string) $data;
			}

			if ($this->options['debugInfo'] && method_exists($data, '__debugInfo')) {
				$data = (array) $data->__debugInfo();
			} elseif ($this->options['jsonSerialize'] && method_exists($data, 'jsonSerialize')) {
				$data = (array) $data->jsonSerialize();
			} elseif ($this->options['toArray'] && method_exists($data, 'toArray')) {
				$data = (array) $data->toArray();
			} else {
				$data = (array) $data;
			}

			$data = array_combine(
				array_map(function ($key) {
					// replace null-byte prefixes of protected and private properties used by php with * (protected)
					// and ~ (private)
					return preg_replace('/^\0.+?\0/', '~', str_replace("\0*\0", '*', $key));
				}, array_keys($data)),
				$this->normalizeEach($data, $context, $limit - 1)
			);

			return $this->cache[$objectHash] = [ '__class__' => $className ] + $data;
		} elseif (is_resource($data)) {
			return [ '__type__' => 'resource' ];
		}

		return $data;
	}

	// Normalize each member of an array (doesn't add metadata for top level)
	public function normalizeEach($data, $context = null, $limit = null) {
		return array_map(function ($item) use ($context, $limit) {
			return $this->normalize($item, $context, $limit);
		}, $data);
	}

	// Normalize a stack trace instance
	public function trace(StackTrace $trace)
	{
		if (! $this->options['traces']) return null;

		if ($this->options['tracesFilter']) $trace = $trace->filter($this->options['tracesFilter']);
		if ($this->options['tracesSkip']) $trace = $trace->skip($this->options['tracesSkip']);
		if ($this->options['tracesLimit']) $trace = $trace->limit($this->options['tracesLimit']);

		return array_map(function ($frame) {
			return [
				'call' => $frame->call,
				'file' => $frame->file,
				'line' => $frame->line,
				'isVendor' => (bool) $frame->vendor
			];
		}, $trace->frames());
	}

	// Normalize an exception instance
	public function exception(/* Throwable */ $exception)
	{
		return [
			'type'     => get_class($exception),
			'message'  => $exception->getMessage(),
			'code'     => $exception->getCode(),
			'file'     => $exception->getFile(),
			'line'     => $exception->getLine(),
			'trace'    => (new Serializer([ 'tracesLimit' => false ]))->trace(StackTrace::from($exception->getTrace())),
			'previous' => $exception->getPrevious() ? $this->exception($exception->getPrevious()) : null
		];
	}
}
