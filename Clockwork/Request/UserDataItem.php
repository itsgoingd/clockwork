<?php namespace Clockwork\Request;

// Data structure representing custom user data item (shown as counters or table)
class UserDataItem
{
	// Data contents (labels and values or table rows)
	protected $data;

	// Describes how the data should be presented ("counters" or "table")
	protected $showAs;

	// Data title (shown as table title in the official app)
	protected $title;

	// Map of human-readable labels for the data contents
	protected $labels;

	public function __construct(array $data)
	{
		$this->data = $data;
	}

	// Set how the item should be presented ("counters" or "table")
	public function showAs($showAs)
	{
		$this->showAs = $showAs;
		return $this;
	}

	// Set data title (shown as table title in the official app)
	public function title($title)
	{
		$this->title = $title;
		return $this;
	}

	// Set a map of human-readable labels for the data contents
	public function labels($labels)
	{
		$this->labels = $labels;
		return $this;
	}

	// Transform contents to a serializable array with metadata
	public function toArray()
	{
		return array_merge($this->data, [
			'__meta' => array_filter([
				'showAs' => $this->showAs,
				'title'  => $this->title,
				'labels' => $this->labels
			])
		]);
	}
}
