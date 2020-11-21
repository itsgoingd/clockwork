<?php namespace Clockwork\Request\Timeline;

// Data structure representing collection of time-based events
class Timeline
{
	// Timeline events
	public $events = [];

	// Create a new timeline, optionally with existing events
	public function __construct($events = [])
	{
		foreach ($events as $event) {
			$this->create($event['description'], $event);
		}
	}

	// Find or create a new event, takes description and optional data - name, start, end, duration, color, data
	public function event($description, $data = [])
	{
		$name = isset($data['name']) ? $data['name'] : $description;

		if ($event = $this->find($name)) return $event;

		return $this->create($description, $data);
	}

	// Create a new event, takes description and optional data - name, start, end, duration, color, data
	public function create($description, $data = [])
	{
		return $this->events[] = new Event($description, $data);
	}

	// Find event by name
	public function find($name)
	{
		foreach ($this->events as $event) {
			if ($event->name == $name) return $event;
		}
	}

	// Merge another timeline instance into the current timeline
	public function merge(Timeline $timeline)
	{
		$this->events = array_merge($this->events, $timeline->events);

		return $this;
	}

	// Finalize timeline, ends all events, sorts them and returns as an array
	public function finalize($start = null, $end = null)
	{
		foreach ($this->events as $event) {
			$event->finalize($start, $end);
		}

		$this->sort();

		return $this->toArray();
	}

	// Sort the timeline events by start time
	public function sort()
	{
		usort($this->events, function ($a, $b) { return $a->start * 1000 - $b->start * 1000; });
	}

	// Return events as an array
	public function toArray()
	{
		return array_map(function ($event) { return $event->toArray(); }, $this->events);
	}
}
