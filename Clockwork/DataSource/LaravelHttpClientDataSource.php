<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\{Serializer, StackTrace};
use Clockwork\Request\Request;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Events\{ConnectionFailed, RequestSending, ResponseReceived};

// Data source for Laravel HTTP client, provides executed HTTP requests
class LaravelHttpClientDataSource extends DataSource
{
	// Event dispatcher instance
	protected $dispatcher;

	// Sent HTTP requests
	protected $requests = [];
	
	// Map of executing requests, keyed by their object hash
	protected $executingRequests = [];
	
	// Whether to collect request and response content (json or form data) and raw content
	protected $collectContent = true;
	protected $collectRawContent = true;

	// Create a new data source instance, takes an event dispatcher as argument
	public function __construct(Dispatcher $dispatcher, $collectContent = true, $collectRawContent = false)
	{
		$this->dispatcher = $dispatcher;

		$this->collectContent = $collectContent;
		$this->collectRawContent = $collectRawContent;
	}
	
	// Add sent notifications to the request
	public function resolve(Request $request)
	{
		$request->httpRequests = array_merge($request->httpRequests, $this->requests);
		
		return $request;
	}
	
	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->requests = [];
		$this->executingRequests = [];
	}
	
	// Listen to the email and notification events
	public function listenToEvents()
	{
		$this->dispatcher->listen(ConnectionFailed::class, function ($event) { $this->connectionFailed($event); });
		$this->dispatcher->listen(RequestSending::class, function ($event) { $this->sendingRequest($event); });
		$this->dispatcher->listen(ResponseReceived::class, function ($event) { $this->responseReceived($event); });
	}
	
	// Collect an executing request
	protected function sendingRequest(RequestSending $event)
	{
		$trace = StackTrace::get()->resolveViewName();
		
		$request = (object) [
			'request'  => (object) [
				'method'  => $event->request->method(),
				'url'     => $this->removeAuthFromUrl($event->request->url()),
				'headers' => $event->request->headers(),
				'content' => $this->collectContent ? $event->request->data() : null,
				'body'    => $this->collectRawContent ? $event->request->body() : null
			],
			'response' => null,
			'stats'    => null,
			'error'    => null, 
			'time'     => microtime(true),
			'trace'    => (new Serializer)->trace($trace)
		];
		
		if ($this->passesFilters([ $request ])) {
			$this->requests[] = $this->executingRequests[spl_object_hash($event->request)] = $request;
		}
	}

	// Update last request with response details and time taken
	protected function responseReceived($event)
	{
		if (! isset($this->executingRequests[spl_object_hash($event->request)])) return;
		
		$request = $this->executingRequests[spl_object_hash($event->request)];
		$stats = $event->response->handlerStats();
				
		$request->duration = (microtime(true) - $request->time) * 1000;
		$request->response = (object) [
			'status'  => $event->response->status(),
			'headers' => $event->response->headers(),
			'content' => $this->collectContent ? $event->response->json() : null,
			'body'    => $this->collectRawContent ? $event->response->body() : null
		];
		$request->stats = (object) [
			'timing' => isset($stats['total_time_us']) ? (object) [
				'lookup' => $stats['namelookup_time_us'] / 1000,
				'connect' => ($stats['pretransfer_time_us'] - $stats['namelookup_time_us']) / 1000,
				'waiting' => ($stats['starttransfer_time_us'] - $stats['pretransfer_time_us']) / 1000,
				'transfer' => ($stats['total_time_us'] - $stats['starttransfer_time_us']) / 1000
			] : null,
			'size' => (object) [
				'upload' => $stats['size_upload'] ?? null,
				'download' => $stats['size_download'] ?? null
			],
			'speed' => (object) [
				'upload' => $stats['speed_upload'] ?? null,
				'download' => $stats['speed_download'] ?? null
			],
			'hosts' => (object) [
				'local' => isset($stats['local_ip']) ? [ 'ip' => $stats['local_ip'], 'port' => $stats['local_port'] ] : null,
				'remote' => isset($stats['primary_ip']) ? [ 'ip' => $stats['primary_ip'], 'port' => $stats['primary_port'] ] : null
			],
			'version' => $stats['http_version'] ?? null
		];
		
		unset($this->executingRequests[spl_object_hash($event->request)]);
	}
	
	// Update last request with error when connection fails
	protected function connectionFailed($event)
	{
		if (! isset($this->executingRequests[spl_object_hash($event->request)])) return;

		$request = $this->executingRequests[spl_object_hash($event->request)];
		
		$request->duration = (microtime(true) - $request->time) * 1000;
		$request->error = 'connection-failed';

		unset($this->executingRequests[spl_object_hash($event->request)]);
	}

	// Removes username and password from the URL
	protected function removeAuthFromUrl($url)
	{
		return preg_replace('#^(.+?://)(.+?@)(.*)$#', '$1$3', $url);
	}
}
