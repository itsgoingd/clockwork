<?php namespace Clockwork\Request;

// Data structure representing custom user data (shown as extra tab in the official app)
class UserData
{
	// Data items (tab contents in the official app)
	protected $data = [];

	// Data title (tab name in the official app)
	protected $title;

	// Add generic user data
	public function data(array $data, $key = null)
	{
		if ($key !== null) {
			return $this->data[$key] = new UserDataItem($data);
		}

		return $this->data[] = new UserDataItem($data);
	}

	// Add user data shown as counters in the official app
	public function counters(array $data)
	{
		return $this->data($data)
			->showAs('counters');
	}

	// Add user data shown as table in the official app
	public function table($title, array $data)
	{
		return $this->data($data)
			->showAs('table')
			->title($title);
	}

	// Set data title (shown as tab name in the official app)
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
