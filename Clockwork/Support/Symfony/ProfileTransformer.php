<?php namespace Clockwork\Support\Symfony;

use Clockwork\Helpers\Serializer;
use Clockwork\Request\{Log, Request};
use Clockwork\Request\Timeline\Timeline;

use Symfony\Component\HttpKernel\Profiler\Profile;

class ProfileTransformer
{
	public function transform(Profile $profile)
	{
		$request = new Request([ 'id' => $profile->getToken() ]);

		$this->transformCacheData($profile, $request);
		$this->transformDoctrineData($profile, $request);
		$this->transformEventsData($profile, $request);
		$this->transformLoggerData($profile, $request);
		$this->transformRequestData($profile, $request);
		$this->transformTimeData($profile, $request);
		$this->transformTwigData($profile, $request);

		$request->subrequests = $this->getSubrequests($profile);

		return $request;
	}

	// Cache collector

	protected function transformCacheData(Profile $profile, Request $request)
	{
		if (! $profile->hasCollector('cache')) return;

		$data = $profile->getCollector('cache');

		$request->cacheQueries = $this->getCacheQueries($data);
		$request->cacheReads   = $data->getTotals()['reads'];
		$request->cacheHits    = $data->getTotals()['hits'];
		$request->cacheWrites  = $data->getTotals()['writes'];
		$request->cacheDeletes = $data->getTotals()['deletes'];
	}

	protected function getCacheQueries($data)
	{
		return array_reduce(array_map(function ($queries, $connection) {
			return array_filter(array_map(function ($query) use ($connection) {
				$value = $query['result'];

				if (! is_array($value) || ! count($value)) return;

				return [
					'connection' => $connection,
					'time'       => $query['start'],
					'type'       => array_values($value)[0] ? 'hit' : 'miss',
					'key'        => array_keys($value)[0],
					'value'      => '',
					'duration'   => $query['end'] - $query['start']
				];
			}, $queries));
		}, $this->unwrap($data->getCalls()), array_keys($this->unwrap($data->getCalls()))), function ($all, $queries) {
			return array_merge($all, $queries);
		}, []);
	}

	// Doctrine collector

	protected function transformDoctrineData(Profile $profile, Request $request)
	{
		if (! $profile->hasCollector('db')) return;

		$data = $profile->getCollector('db');

		$request->databaseDuration = $data->getTime();
		$request->databaseQueries = $this->getQueries($data);
	}

	protected function getQueries($data)
	{
		return array_reduce(array_map(function ($queries, $connection) {
			return array_filter(array_map(function ($query) use ($connection) {
				return [
					'query'      => $this->createRunnableQuery($query['sql'], $this->unwrap($query['params'])),
					'duration'   => $query['executionMS'] * 1000,
					'connection' => $connection
				];
			}, $queries));
		}, $data->getQueries(), array_keys($data->getQueries())), function ($all, $queries) {
			return array_merge($all, $queries);
		}, []);
	}

	protected function createRunnableQuery($query, $bindings)
	{
		foreach ($bindings as $binding) {
			$binding = \Doctrine\Bundle\DoctrineBundle\Twig\DoctrineExtension::escapeFunction($binding);

			// escape backslashes in the binding (preg_replace requires to do so)
			$binding = str_replace('\\', '\\\\', $binding);

			$query = preg_replace('/\?/', $binding, $query, 1);
		}

		// highlight keywords
		$keywords = [
			'select', 'insert', 'update', 'delete', 'where', 'from', 'limit', 'is', 'null', 'having', 'group by',
			'order by', 'asc', 'desc'
		];
		$regexp = '/\b' . implode('\b|\b', $keywords) . '\b/i';

		$query = preg_replace_callback($regexp, function ($match) { return strtoupper($match[0]); }, $query);

		return $query;
	}

	// Events collector

	protected function transformEventsData(Profile $profile, Request $request)
	{
		if (! $profile->hasCollector('events')) return;

		$data = $profile->getCollector('events');

		$request->events = $this->getEvents($data);
	}

	protected function getEvents($data)
	{
		$handledEvents = array_values(array_reduce($this->unwrap($data->getCalledListeners('event_dispatcher')), function ($events, $listener) {
			if (! isset($events[$listener['event']])) {
				$events[$listener['event']] = [ 'event' => $listener['event'], 'listeners' => [] ];
			}

			$events[$listener['event']]['listeners'][] = $listener['stub'];

			return $events;
		}, []));

		$orphanedEvents = array_map(function ($event) {
			return [ 'event' => $event ];
		}, $this->unwrap($data->getOrphanedEvents('event_dispatcher')));

		return array_merge($handledEvents, $orphanedEvents);
	}

	// Log collector

	protected function transformLoggerData(Profile $profile, Request $request)
	{
		if (! $profile->hasCollector('logger')) return;

		$data = $profile->getCollector('logger');

		$request->log()->merge($this->getLog($data));
	}

	protected function getLog($data)
	{
		$messages = array_map(function ($log) {
			$context = $log['context'] ?? [];
			$replacements = array_filter($context, function ($v) { return ! is_array($v) && ! is_object($v) && ! is_resource($v); });

			return [
				'message' => str_replace(
					array_map(function ($v) { return "{{$v}}"; }, array_keys($replacements)),
					array_values($replacements),
					$log['message']
				),
				'context' => (new Serializer)->normalize($log['context']),
				'level'   => strtolower($log['priorityName']),
				'time'    => $log['timestamp']
			];
		}, $this->unwrap($data->getLogs()));

		return new Log($messages);
	}

	// Request collector

	protected function transformRequestData(Profile $profile, Request $request)
	{
		if (! $profile->hasCollector('request')) return;

		$data = $profile->getCollector('request');

		$request->method         = $data->getMethod();
		$request->uri            = $data->getPathInfo();
		$request->controller     = $this->getController($data);
		$request->responseStatus = $data->getStatusCode();
		$request->headers        = $this->unwrap($data->getRequestHeaders());
		$request->getData        = $this->unwrap($data->getRequestQuery());
		$request->postData       = $this->unwrap($data->getRequestRequest());
		$request->cookies        = $this->unwrap($data->getRequestCookies());
		$request->sessionData    = (new Serializer)->normalizeEach($this->unwrap($data->getSessionAttributes()));
	}

	protected function getController($data)
	{
		$controller = $this->unwrap($data->getController());

		if (! is_array($controller)) return $controller;

		return isset($controller['method'])
			? "{$controller['class']}@{$controller['method']}"
			: $controller['class'];
	}

	// Time collector

	protected function transformTimeData(Profile $profile, Request $request)
	{
		if (! $profile->hasCollector('time')) return;

		$data = $profile->getCollector('time');

		$request->time         = $data->getStartTime() / 1000;
		$request->responseTime = $this->getResponseTime($data);

		$request->timeline()->merge($this->getTimeline($data));
	}

	protected function getResponseTime($data)
	{
		$lastEvent = $data->getEvents()['__section__'];

		return ($lastEvent->getOrigin() + $lastEvent->getDuration()) / 1000;
	}

	protected function getTimeline($data)
	{
		$events = array_map(function ($event, $name) {
			if ($name == '__section__') {
				$name = 'Application runtime';
			} elseif ($name == '__section__.child') {
				$name = 'Subrequest';
			}

			return [
				'start'       => ($event->getOrigin() + $event->getStartTime()) / 1000,
				'end'         => ($event->getOrigin() + $event->getEndTime()) / 1000,
				'duration'    => $event->getDuration(),
				'description' => $name,
				'data'        => []
			];
		}, $data->getEvents(), array_keys($data->getEvents()));

		$topEvent = $data->getEvents()['__section__'];
		array_unshift($events, [
			'start'       => $start = $data->getStartTime() / 1000,
			'end'         => $end = ($topEvent->getOrigin() + $topEvent->getStartTime()) / 1000,
			'duration'    => ($end - $start) * 1000,
			'description' => 'Symfony initialization',
			'data'        => []
		]);

		return new Timeline($events);
	}

	// Twig collector

	protected function transformTwigData(Profile $profile, Request $request)
	{
		if (! $profile->hasCollector('twig')) return;

		$data = $profile->getCollector('twig');

		$request->viewsData = $this->getViews($data);
	}

	protected function getViews($data)
	{
		return array_map(function ($template) {
			return [
				'description' => 'Rendering a view',
				'data' => [ 'name' => $template, 'data' => [] ]
			];
		}, array_keys($data->getTemplates()));
	}

	protected function getSubrequests($profile)
	{
		return array_map(function ($child) {
			return [
				'url'  => urlencode($child->getCollector('request')->getPathInfo()),
				'id'   => $child->getToken(),
				'path' => null
			];
		}, $profile->getChildren());
	}

	protected function unwrap($data)
	{
		if ($data instanceof \Symfony\Component\VarDumper\Cloner\Data) {
			return $data->getValue(true);
		} elseif ($data instanceof \Symfony\Component\HttpFoundation\ParameterBag) {
			return array_map(function ($val) { return $val->getValue(); }, $data->all());
		} elseif (is_array($data)) {
			return array_map(function ($item) { return $this->unwrap($item); }, $data);
		}

		return $data;
	}
}
