<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;
use Clockwork\Request\Timeline\Timeline;
use Clockwork\Support\Swift\SwiftPluginClockworkTimeline;

use Swift_Mailer;

/**
 * Data source for Swift, provides mail log
 */
class SwiftDataSource extends DataSource
{
	protected $swift;

	/**
	 * Timeline data structure
	 */
	protected $timeline;

	/**
	 * Create a new data source, takes Swift_Mailer instance as an argument
	 */
	public function __construct(Swift_Mailer $swift)
	{
		$this->swift = $swift;
		$this->timeline = new Timeline;
	}

	// Start listening to the events
	public function listenToEvents()
	{
		$this->swift->registerPlugin(new SwiftPluginClockworkTimeline($this->timeline));
	}

	/**
	 * Adds email data to the request
	 */
	public function resolve(Request $request)
	{
		$request->emailsData = array_merge($request->emailsData, $this->timeline->finalize());

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->timeline = new Timeline;
	}
}
