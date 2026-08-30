<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;
use Clockwork\Request\Timeline\Timeline;

use Illuminate\Contracts\Events\Dispatcher;

// Data source for Laravel Scout component, provides searches
class LaravelScoutDataSource extends DataSource
{
	// Event dispatcher
	protected $dispatcher;

	// Timeline data structure for collected searches
	protected $searches;

	// Create a new data source instance, takes an event dispatcher as argument
	public function __construct(Dispatcher $dispatcher)
	{
		$this->dispatcher = $dispatcher;

		$this->searches = new Timeline;
	}

	// Adds rendered searches to the request
	public function resolve(Request $request)
	{
		//$request->searchesData = array_merge($request->searchesData, $this->searches->finalize());

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->searches = new Timeline;
	}

	// Listen to the search events
	public function listenToEvents()
	{
		$this->dispatcher->listen(\Laravel\Scout\Events\ModelsFlushed::class, function ($event) {
			$this->searches->event('Flush models from search', [
				'name'  => 'model ' . get_class($event->models[0]),
				'start' => $time = microtime(true),
				'end'   => $time,
			]);
		});

		$this->dispatcher->listen(\Laravel\Scout\Events\ModelsImported::class, function ($event) {
			$this->searches->event('Import models into search', [
				'name'  => 'model ' . get_class($event->models[0]),
				'start' => $time = microtime(true),
				'end'   => $time,
			]);
		});
	}
}
