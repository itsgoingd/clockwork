<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline\Timeline;

use Illuminate\Contracts\Events\Dispatcher;

// Data source for Laravel views component, provides rendered views
class LaravelViewsDataSource extends DataSource
{
	// Event dispatcher
	protected $dispatcher;

	// Timeline data structure for collected views
	protected $views;

	// Whether we should collect view data
	protected $collectData = false;

	// Create a new data source instance, takes an event dispatcher as argument
	public function __construct(Dispatcher $dispatcher, $collectData = false)
	{
		$this->dispatcher = $dispatcher;

		$this->collectData = $collectData;

		$this->views = new Timeline;
	}

	// Adds rendered views to the request
	public function resolve(Request $request)
	{
		$request->viewsData = array_merge($request->viewsData, $this->views->finalize());

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->views = new Timeline;
	}

	// Listen to the views events
	public function listenToEvents()
	{
		$this->dispatcher->listen('composing:*', function ($view, $data = null) {
			if (is_string($view) && is_array($data)) { // Laravel 5.4 wildcard event
				$view = $data[0];
			}

			$data = array_filter(
				$this->collectData ? $view->getData() : [],
				function ($v, $k) { return strpos($k, '__') !== 0; },
				\ARRAY_FILTER_USE_BOTH
			);

			$this->views->event('Rendering a view', [
				'name'  => 'view ' . $view->getName(),
				'start' => $time = microtime(true),
				'end'   => $time,
				'data'  => [
					'name' => $view->getName(),
					'data' => (new Serializer)->normalize($data)
				]
			]);
		});
	}
}
