<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\StackTrace;
use Clockwork\Request\Request;

use Illuminate\Contracts\Events\Dispatcher;

// Data source for Laravel events component, provides fired events
class LaravelEventsDataSource extends DataSource
{
	// Event dispatcher instance
	protected $dispatcher;

	// Fired events
	protected $events = [];

	// Whether framework events should be collected
	protected $ignoredEvents = false;

	// Create a new data source instance, takes an event dispatcher and additional options as arguments
	public function __construct(Dispatcher $dispatcher, $ignoredEvents = [])
	{
		$this->dispatcher = $dispatcher;

		$this->ignoredEvents = is_array($ignoredEvents)
			? array_merge($ignoredEvents, $this->defaultIgnoredEvents()) : [];
	}

	// Adds fired events to the request
	public function resolve(Request $request)
	{
		$request->events = array_merge($request->events, $this->events);

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->events = [];
	}

	// Start listening to the events
	public function listenToEvents()
	{
		$this->dispatcher->listen('*', function ($event = null, $data = null) {
			if (method_exists($this->dispatcher, 'firing')) { // Laravel 5.0 - 5.3
				$data = func_get_args();
				$event = $this->dispatcher->firing();
			}

			$this->registerEvent($event, $data);
		});
	}

	// Collect a fired event, prepares data for serialization and resolves registered listeners
	protected function registerEvent($event, array $data)
	{
		if (! $this->shouldCollect($event)) return;

		$trace = StackTrace::get()->resolveViewName();

		$event = [
			'event'     => $event,
			'data'      => (new Serializer)->normalize(count($data) == 1 && isset($data[0]) ? $data[0] : $data),
			'time'      => microtime(true),
			'listeners' => $this->findListenersFor($event),
			'trace'     => (new Serializer)->trace($trace)
		];

		if ($this->passesFilters([ $event ])) {
			$this->events[] = $event;
		}
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
			'Laravel\\\\.+',
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
