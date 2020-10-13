<?php namespace Clockwork\Helpers;

use Clockwork\Request\Request;

// Generates Server-Timing header value
class ServerTiming
{
	// Performance metrics to include
	protected $metrics = [];

	// Add a performance metric
	public function add($metric, $value, $description)
	{
		$this->metrics[] = [ 'metric' => $metric, 'value' => $value, 'description' => $description ];

		return $this;
	}

	// Generate the header value
	public function value()
	{
		return implode(', ', array_map(function ($metric) {
			return "{$metric['metric']}; dur={$metric['value']}; desc=\"{$metric['description']}\"";
		}, $this->metrics));
	}

	// Create a new instance from a Clockwork request
	public static function fromRequest(Request $request, $eventsCount = 10)
	{
		$header = new static;

		$header->add('app', $request->getResponseDuration(), 'Application');

		if ($request->getDatabaseDuration()) {
			$header->add('db', $request->getDatabaseDuration(), 'Database');
		}

		// add timeline events limited to a set number so the header doesn't get too large
		foreach (array_slice($request->timeline()->events, 0, $eventsCount) as $i => $event) {
			$header->add("timeline-event-{$i}", $event->duration(), $event->description);
		}

		return $header;
	}
}
