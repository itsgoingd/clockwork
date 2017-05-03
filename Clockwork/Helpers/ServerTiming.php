<?php namespace Clockwork\Helpers;

use Clockwork\Request\Request;

class ServerTiming
{
	protected $metricsevents = array();

	public function add($metric, $value, $description)
	{
		$this->metrics[] = array('metric' => $metric, 'value' => $value, 'description' => $description);

		return $this;
	}

	public function value()
	{
		return implode(', ', array_map(function ($metric) {
			return "{$metric['metric']}={$metric['value']}; \"{$metric['description']}\"";
		}, $this->metrics));
	}

	public static function fromRequest(Request $request, $eventsCount = 10)
	{
		$header = new static;

		$header->add('app', $request->getResponseDuration(), 'Application');

		if ($request->getDatabaseDuration()) {
			$header->add('db', $request->getDatabaseDuration(), 'Database');
		}

		// add timeline events limited to a set number so the header doesn't get too large
		foreach (array_slice($request->timelineData, 0, $eventsCount) as $i => $event) {
			$header->add("timeline-event-{$i}", $event['duration'], $event['description']);
		}

		return $header;
	}
}
