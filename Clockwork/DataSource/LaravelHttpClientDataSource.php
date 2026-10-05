<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\{Serializer, StackTrace};
use Clockwork\Request\Request;
use Clockwork\Support\Guzzle\ClockworkCapturingStream;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Events\{ConnectionFailed, RequestSending, ResponseReceived};
use Psr\Http\Message\{RequestInterface, ResponseInterface};

// Data source for Laravel HTTP client, provides executed HTTP requests
class LaravelHttpClientDataSource extends DataSource
{
	use Concerns\HttpCollectResponseBody;

	// Event dispatcher instance
	protected $dispatcher;
	protected $http;

	// Sent HTTP requests
	protected $requests = [];

	// Map of executing requests, keyed by their object hash
	protected $executingRequests = [];
	protected $executingPsrRequests = [];

	// Whether to collect request and response content (json or form data) and raw content
	protected $collectContent = true;
	protected $collectRawContent = true;
	protected $collectStreamContent = false;

	// Create a new data source instance, takes an event dispatcher as argument
	public function __construct(Dispatcher $dispatcher, ?Factory $http = null, $collectContent = true, $collectRawContent = false, $collectStreamContent = false, $maxResponseDataSize = null)
	{
		$this->dispatcher = $dispatcher;
		$this->http = $http;

		$this->collectContent = $collectContent;
		$this->collectRawContent = $collectRawContent;
		$this->collectStreamContent = $collectStreamContent;
		$this->maxResponseDataSize = $maxResponseDataSize;
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
		$this->executingPsrRequests = [];
	}

	// Listen to the email and notification events
	public function listenToEvents()
	{
		$this->dispatcher->listen(ConnectionFailed::class, function ($event) { $this->connectionFailed($event); });
		$this->dispatcher->listen(RequestSending::class, function ($event) { $this->sendingRequest($event); });
		$this->dispatcher->listen(ResponseReceived::class, function ($event) { $this->responseReceived($event); });

		if ($this->collectStreamContent && $this->http && method_exists($this->http, 'globalMiddleware')) {
			$this->http->globalMiddleware($this->streamCapturingMiddleware());
		}
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
			$this->requests[] = $this->executingRequests[spl_object_id($event->request)] = $request;
			$this->executingPsrRequests[spl_object_id($event->request->toPsrRequest())] = $request;
		}
	}

	// Update last request with response details and time taken
	protected function responseReceived($event)
	{
		if (! isset($this->executingRequests[spl_object_id($event->request)])) return;

		$request = $this->executingRequests[spl_object_id($event->request)];
		$stats = $event->response->handlerStats();

		$responseData = $this->collectResponseBody($event->response->toPsrResponse()->getBody());

		$request->duration = (microtime(true) - $request->time) * 1000;
		$request->response = (object) [
			'status'    => $event->response->status(),
			'headers'   => $event->response->headers(),
			'content'   => $responseData->content,
			'body'      => $responseData->body,
			'stream'    => $responseData->stream,
			'truncated' => $responseData->truncated
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


		unset($this->executingRequests[spl_object_id($event->request)]);
		unset($this->executingPsrRequests[spl_object_id($event->request->toPsrRequest())]);
	}

	protected function streamCapturingMiddleware()
	{
		return function (callable $handler) {
			return function (RequestInterface $request, array $options) use ($handler) {
				return $handler($request, $options)->then(function (ResponseInterface $response) use ($request) {
					$clockworkRequest = $this->executingPsrRequests[spl_object_id($request)] ?? null;

					if ($clockworkRequest && ! $response->getBody()->isSeekable()) {
						return $response->withBody(new ClockworkCapturingStream(
							$response->getBody(), $this->collectStreamResponseBody($clockworkRequest)
						));
					}

					return $response;
				});
			};
		};
	}

	// Update last request with error when connection fails
	protected function connectionFailed($event)
	{
		if (! isset($this->executingRequests[spl_object_id($event->request)])) return;

		$request = $this->executingRequests[spl_object_id($event->request)];

		$request->duration = (microtime(true) - $request->time) * 1000;
		$request->error = 'connection-failed';

		unset($this->executingRequests[spl_object_id($event->request)]);
		unset($this->executingPsrRequests[spl_object_id($event->request->toPsrRequest())]);
	}

	// Removes username and password from the URL
	protected function removeAuthFromUrl($url)
	{
		return preg_replace('#^(.+?://)(.+?@)(.*)$#', '$1$3', $url);
	}
}
