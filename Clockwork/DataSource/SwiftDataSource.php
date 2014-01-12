<?php
namespace Clockwork\DataSource;

use Clockwork\Request\Request;
use Clockwork\Request\Timeline;
use Clockwork\Support\Swift\SwiftPluginClockworkTimeline;
use Swift_Mailer;

/**
 * Data source for Swift, provides mail log
 */
class SwiftDataSource extends DataSource
{
	/**
	 * Timeline data structure
	 */
	protected $timeline;

	/**
	 * Create a new data source, takes Swift_Mailer instance as an argument
	 */
	public function __construct(Swift_Mailer $swift)
	{
		$this->timeline = new Timeline();

		$swift->registerPlugin(new SwiftPluginClockworkTimeline($this->timeline));
	}

	/**
	 * Adds email data to the request
	 */
	public function resolve(Request $request)
	{
		$request->emailsData = array_merge($request->emailsData, $this->timeline->finalize());

		return $request;
	}
}
