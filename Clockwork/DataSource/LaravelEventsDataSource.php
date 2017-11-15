<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\StackTrace;
use Clockwork\Request\Request;

use Illuminate\Events\Dispatcher;

/**
 * Data source for Laravel events component, provides fired events
 */
class LaravelEventsDataSource extends DataSource
{
	// Event dispatcher
	protected $dispatcher;

	// Fired events
	protected $events = [];

	// Whether framework events should be collected
	protected $ignoredEvents = false;

	// Create a new data source instance, takes an event dispatcher as argument
	public function __construct(Dispatcher $dispatcher, $ignoredEvents = [])
	{
		$this->dispatcher = $dispatcher;
		$this->ignoredEvents = is_array($ignoredEvents)
			? array_merge($ignoredEvents, $this->defaultIgnoredEvents()) : [];
	}

	// Start listening to the events
	public function listenToEvents()
	{
		$this->dispatcher->listen('*', function ($event = null, $data = null) {
			if (method_exists($this->dispatcher, 'firing')) { // Laravel 4.1 - 5.3
				$data = func_get_args();
				$event = $this->dispatcher->firing();
			} elseif (count(func_get_args()) > 2 || ! is_array($data)) { // Laravel 4.0
				$data = func_get_args();
				$event = array_pop($data);
			}

			$this->registerEvent($event, $data);
		});
	}

	// Adds fired events to the request
	public function resolve(Request $request)
	{
		$request->events = array_merge($request->events, $this->events);

		return $request;
	}

	// Registers a new event, prepares data for serialization and resolves registered listeners
	protected function registerEvent($event, array $data)
	{
		if (! $this->shouldCollect($event)) return;

		$firedAt = StackTrace::get()->firstNonVendor([ 'itsgoingd', 'laravel', 'illuminate' ]);

		$this->events[] = [
			'event'     => $event,
			'data'      => Serializer::simplify(count($data) == 1 && isset($data[0]) ? $data[0] : $data),
			'time'      => microtime(true),
			'listeners' => $this->findListenersFor($event),
			'file'      => $firedAt->shortPath,
			'line'      => $firedAt->line
		];
	}

	// Returns registered listeners for the specified event
	protected function findListenersFor($event)
	{
		$listener = $this->dispatcher->getListeners($event)[0];

		return array_filter(array_map(function ($listener) {
			if ($listener instanceof \Closure) {
				// Laravel 5.4+ (and earlier versions in some cases) wrap the listener into a closure,
				// attempt to resolve the original listener
				$use = (new \ReflectionFunction($listener))->getStaticVariables();
				$listener = isset($use['listener']) ? $use['listener'] : $listener;
			}

			if (is_string($listener)) {
				return $listener;
			} elseif (is_array($listener) && count($listener) == 2) {
				if (is_object($listener[0])) {
					return get_class($listener[0]) . '@' . $listener[1];
				} else {
					return $listener[0] . '::' . $listener[1];
				}
			} elseif ($listener instanceof \Closure) {
				$listener = new \ReflectionFunction($listener);

				if (strpos($listener->getNamespaceName(), 'Clockwork\\') === 0) { // skip our own listeners
					return;
				}

				$filename = str_replace(base_path(), '', $listener->getFileName());
				$startLine = $listener->getStartLine();
				$endLine = $listener->getEndLine();

				return "Closure ({$filename}:{$startLine}-{$endLine})";
			}
		}, $this->dispatcher->getListeners($event)));
	}

	// Returns whether the event should be collected (depending on ignored events)
	protected function shouldCollect($event)
	{
		return ! preg_match('/^(?:' . implode('|', $this->ignoredEvents) . ')$/', $event);
	}

	// Returns default ignored events (framework-specific events)
	protected function defaultIgnoredEvents()
	{
		return [
			'Illuminate\\\\.+',
			'auth\.(?:attempt|login|logout)',
			'artisan\.start',
			'bootstrapped:.+',
			'composing:.+',
			'creating:.+',
			'illuminate\.query',
			'connection\..+',
			'eloquent\..+',
			'kernel\.handled',
			'illuminate\.log',
			'mailer\.sending',
			'router\.(?:before|after|matched)',
			'router.filter:.+',
			'locale\.changed',
			'clockwork\..+'
		];
	}
}
