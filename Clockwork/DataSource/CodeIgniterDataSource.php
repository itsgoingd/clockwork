<?php

namespace Clockwork\DataSource;

use Clockwork\Request\Request;
use Clockwork\Request\Timeline;


/**
 * Data source for CodeIgniter, provides database queries and routes.
 */
class CodeIgniterDataSource extends DataSource
{
	/**
	 * Clockwork Timeline to allow hooks to start/stop events.
	 */
	protected $timeline;
	
	/**
	 * Construct the Timeline. The HookClockwork class will add events.
	 */
	public function __construct()
	{
		$this->timeline = new Timeline;
	}
	
	/**
	 * Adds Database queries, URI, Method and Controller. Also finalizes
	 * the Timeline.
	 */
	public function resolve(Request $request)
	{
		$CI = &get_instance();
		
		$request->uri = $CI->uri->ruri_string();
		$request->controller = $CI->router->fetch_class();
		$request->method = $CI->router->fetch_method();
		
		$request->timelineData = $this->timeline->finalize($request->time);
		
		$request->databaseQueries = $this->getDatabaseQueries();
		
		return $request;
	}
	
	/**
	 * Returns an array of queries and their durations made by CodeIgniter.
	 */
	protected function getDatabaseQueries()
	{
		$CI = &get_instance();
		
		$databaseQueries = array();
		$queries = array_combine($CI->db->query_times, $CI->db->queries);
		foreach ($queries as $time => $query) {
			$databaseQueries[] = array(
				'query'		=> $query,
				'duration'	=> $time
			);
		}
		
		return $databaseQueries;
	}
	
	/**
	 * Start an Event in the CodeIgniter Timeline.
	 */
	public function startEvent($event, $description)
	{
		$this->timeline->startEvent($event, $description);
	}
	
	/**
	 * End an Event in the CodeIgniter Timeline.
	 */
	public function endEvent($event)
	{
		$this->timeline->endEvent($event);
	}
	
}
