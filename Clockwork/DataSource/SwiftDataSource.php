<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;
use Clockwork\Request\Timeline\Timeline;
use Clockwork\Support\Swift\SwiftPluginClockworkTimeline;

use Swift_Mailer;

// Data source for Swift mailer, provides sent emails
class SwiftDataSource extends DataSource
{
	// Swift instance
	protected $swift;

	// Clockwork timeline instance
	protected $timeline;

	// Create a new data source, takes a Swift instance as an argument
	public function __construct(Swift_Mailer $swift)
	{
		$this->swift = $swift;

		$this->timeline = new Timeline;
	}

	// Listen to the email events
	public function listenToEvents()
	{
		$this->swift->registerPlugin(new SwiftPluginClockworkTimeline($this->timeline));
	}

	// Adds send emails to the request
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
