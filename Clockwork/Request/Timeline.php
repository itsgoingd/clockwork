<?php
namespace Clockwork\Request;

/**
 * Data structure for application timeline, used to generate graph of application in client app
 */
class Timeline
{
	/**
	 * Timeline data
	 */
	public $data = array();

	/**
	 * Add a new event
	 */
	public function addEvent($name, $description, $start_time, $end_time, array $data = array())
	{
		$this->data[$name] = array(
			'start'       => $start_time,
			'end'         => $end_time,
			'duration'    => null,
			'description' => $description,
			'data'        => $data,
		);
	}

	/**
	 * Start recording a new event, expects name, description and optional time as arguments, if time is not provided,
	 * current time will be used, if time equals 'start', request time will be used
	 */
	public function startEvent($name, $description, $time = null, array $data = array())
	{
		$this->data[$name] = array(
			'start'       => $time ? $time : microtime(true),
			'end'         => null,
			'duration'    => null,
			'description' => $description,
			'data'        => $data,
		);
	}

	/**
	 * End recording of event specified by name argument, throws exception if specified event is not found
	 */
	public function endEvent($name)
	{
		if (!isset($this->data[$name]))
			return false;

		$this->data[$name]['end'] = microtime(true);

		if (is_numeric($this->data[$name]['start']))
			$this->data[$name]['duration'] = ($this->data[$name]['end'] - $this->data[$name]['start']) * 1000;
	}

	/**
	 * End all unfinished events
	 */
	public function finalize($start = null, $end = null)
	{
		foreach ($this->data as &$item) {
			if ($item['start'] == 'start' && $start)
				$item['start'] = $start;

			if (!$item['end'])
				$item['end'] = $end ? $end : microtime(true);

			$item['duration'] = ($item['end'] - $item['start']) * 1000;
		}

		uasort($this->data, function($a, $b){
			return $a['start'] * 1000 - $b['start'] * 1000;
		});

		return $this->data;
	}

	/**
	 * Return timeline data as array
	 */
	public function toArray()
	{
		return $this->data;
	}
}
