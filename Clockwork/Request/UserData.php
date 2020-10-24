<?php namespace Clockwork\Request;

// Data structure representing custom user data
class UserData
{
	// Data items
	protected $data = [];

	// Data title
	protected $title;

	// Add generic user data
	public function data(array $data, $key = null)
	{
		if ($key !== null) {
			return $this->data[$key] = new UserDataItem($data);
		}

		return $this->data[] = new UserDataItem($data);
	}

	// Add user data shown as counters
	public function counters(array $data)
	{
		return $this->data($data)
			->showAs('counters');
	}

	// Add user data shown as table
	public function table($title, array $data)
	{
		return $this->data($data)
			->showAs('table')
			->title($title);
	}

	// Set data title
	public function title($title)
	{
		$this->title = $title;
		return $this;
	}

	// Transform data and all contents to a serializable array with metadata
	public function toArray()
	{
		return array_merge(
			array_map(function ($data) { return $data->toArray(); }, $this->data),
			[ '__meta' => array_filter([ 'title' => $this->title ]) ]
		);
	}
}
