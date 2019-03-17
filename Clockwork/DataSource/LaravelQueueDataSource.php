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
				'connection' => $connection,
				'queue'      => $queue,
				'name'       => $payload['displayName'],
				'data'       => isset($payload['data']['command']) ? $payload['data']['command'] : null,
				'maxTries'   => $payload['maxTries'],
				'timeout'    => $payload['timeout']
			]);

			return [];
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

	/**
	 * Registers a new queue job, resolves caller file and line no
	 */
	public function registerJob(array $job)
	{
		$trace = StackTrace::get()->resolveViewName();
		$caller = $trace->firstNonVendor([ 'itsgoingd', 'laravel', 'illuminate' ]);

		$this->jobs[] = array_merge($job, [
			'file'  => $caller->shortPath,
			'line'  => $caller->line,
			'trace' => $this->collectStackTraces ? (new Serializer)->trace($trace->framesBefore($caller)) : null
		]);
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
