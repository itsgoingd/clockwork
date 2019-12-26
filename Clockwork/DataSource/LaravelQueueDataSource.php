<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\StackTrace;
use Clockwork\Request\Request;

use Illuminate\Queue\Queue;

/**
 * Data source for Laravel queue component, provides dispatched queue jobs
 */
class LaravelQueueDataSource extends DataSource
{
	/**
	 * Queue instance
	 */
	protected $queue;

	/**
	 * Dispatched queue commands
	 */
	protected $jobs = [];

	// Clockwork ID of the current request
	protected $currentRequestId;

	/**
	 * Create a new data source instance, takes an event dispatcher as argument
	 */
	public function __construct(Queue $queue)
	{
		$this->queue = $queue;
	}

	/**
	 * Start listening to queue events
	 */
	public function listenToEvents()
	{
		$this->queue->createPayloadUsing(function ($connection, $queue, $payload) {
			$this->registerJob([
				'id'         => $id = (new Request)->id,
				'connection' => $connection,
				'queue'      => $queue,
				'name'       => $payload['displayName'],
				'data'       => isset($payload['data']['command']) ? $payload['data']['command'] : null,
				'maxTries'   => $payload['maxTries'],
				'timeout'    => $payload['timeout'],
				'time'       => microtime(true)
			]);

			return [ 'clockwork_id' => $id, 'clockwork_parent_id' => $this->currentRequestId ];
		});
	}

	/**
	 * Adds dispatched queue jobs to the request
	 */
	public function resolve(Request $request)
	{
		$request->queueJobs = array_merge($request->queueJobs, $this->getJobs());

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->jobs = [];
	}

	// Set Clockwork ID of the current request
	public function setCurrentRequestId($requestId)
	{
		$this->currentRequestId = $requestId;
		return $this;
	}

	/**
	 * Registers a new queue job, resolves caller file and line no
	 */
	public function registerJob(array $job)
	{
		$trace = StackTrace::get()->resolveViewName();

		$job = array_merge($job, [
			'trace' => $shortTrace = (new Serializer)->trace($trace),
			'file'  => isset($shortTrace[0]) ? $shortTrace[0]['file'] : null,
			'line'  => isset($shortTrace[0]) ? $shortTrace[0]['line'] : null
		]);

		if ($this->passesFilters([ $job ])) {
			$this->jobs[] = $job;
		}
	}

	/**
	 * Returns an array of dispatched queue jobs commands in Clockwork metadata format
	 */
	protected function getJobs()
	{
		return array_map(function ($query) {
			return array_merge($query, [
				'data' => isset($query['data']) ? (new Serializer)->normalize($query['data']) : null
			]);
		}, $this->jobs);
	}
}
