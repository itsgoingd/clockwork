<?php namespace Clockwork\Request\Timeline;

// Data structure representing a single timeline event with fluent API
class Event
{
	// Event description
	public $description;
	// Unique event name
	public $name;

	// Start time
	public $start;
	// End time
	public $end;

	// Color (blue, red, green, purple, grey)
	public $color;
	// Additional event data
	public $data;

	public function __construct($description, $data = [])
	{
		$this->description = $description;
		$this->name = isset($data['name']) ? $data['name'] : $description;

		$this->start = isset($data['start']) ? $data['start'] : null;
		$this->end = isset($data['end']) ? $data['end'] : null;

		$this->color = isset($data['color']) ? $data['color'] : null;
		$this->data = isset($data['data']) ? $data['data'] : null;
	}

	// Begin the event at current time
	public function begin()
	{
		$this->start = microtime(true);

		return $this;
	}

	// End the event at current time
	public function end()
	{
		$this->end = microtime(true);

		return $this;
	}

	// Begin the event, execute the passed in closure and end the event
	public function run(\Closure $closure)
	{
		$this->begin();

		$closure();

		return $this->end();
	}

	// Set or retrieve event duration (in ms), event can be defined with both start and end time or just a single time and duration
	public function duration($duration = null)
	{
		if (! $duration) return ($this->start && $this->end) ? ($this->end - $this->start) * 1000 : 0;

		if ($this->start) $this->end = $this->start + $duration / 1000;
		if ($this->end) $this->start = $this->end - $duration / 1000;

		return $this;
	}

	// Finalize the event, ends the event, fills in start time if empty and limits the start and end time
	public function finalize($start = null, $end = null)
	{
		$end = $end ?: microtime(true);

		$this->start = $this->start ?: $start;
		$this->end = $this->end ?: $end;

		if ($this->start < $start) $this->start = $start;
		if ($this->end > $end) $this->end = $end;
	}

	// Fluent API
	public function __call($method, $parameters)
	{
		if (! count($parameters)) return $this->$method;

		$this->$method = $parameters[0];

		return $this;
	}

	// Return an array representation of the event
	public function toArray()
	{
		return [
			'description' => $this->description,
			'start'       => $this->start,
			'end'         => $this->end,
			'duration'    => $this->duration(),
			'color'       => $this->color,
			'data'        => $this->data
		];
	}
}
