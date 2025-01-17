<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\{Serializer, StackTrace};
use Clockwork\Request\Request;

use GuzzleHttp\{Client, HandlerStack, TransferStats};
use GuzzleHttp\Exception\{GuzzleException, RequestException};
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

// Data source for Guzzle HTTP client, provides executed HTTP requests
class GuzzleDataSource extends DataSource
{
	// Sent HTTP requests
	protected $requests = [];
	
	// Whether to collect request and response content (json or form data) and raw content
	protected $collectContent = true;
	protected $collectRawContent = true;

	// Create a new data source instance
	public function __construct($collectContent = true, $collectRawContent = false)
	{
		$this->collectContent = $collectContent;
		$this->collectRawContent = $collectRawContent;
	}

	// Returns a new Guzzle instance, pre-configured with Clockwork support
	public function instance(array $config = [])
	{
		return new Client($this->configure($config));
	}
	
	// Updates Guzzle configuration array with Clockwork support
	public function configure(array $config = [])
	{
		$handler = $config['handler'] ?? HandlerStack::create();
		
		$handler->push($this);
		
		$config['handler'] = $handler;
		
		return $config;
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
	}
	
	// Guzzle middleware implemenation, that does the requests logging itself
	public function __invoke(callable $handler): callable
	{
		return function(RequestInterface $request, array $options) use ($handler): PromiseInterface {
			$time = microtime(true);
			$stats = null;
			
			$originalOnStats = $options['on_stats'] ?? null;
			$options['on_stats'] = function (TransferStats $transferStats) use (&$stats, $originalOnStats) {
				$stats = $transferStats->getHandlerStats();
				if ($originalOnStats) $originalOnStats($transferStats);
			};

			return $handler($request, $options)
				->then(function(ResponseInterface $response) use ($request, $time, $stats) {
					$this->collectRequest($request, $response, $time, $stats);

					return $response;
				}, function(GuzzleException $exception) use ($request, $time, $stats) {
					$response = $exception instanceof RequestException ? $exception->getResponse() : null;
					$this->collectRequest($request, $response, $time, $stats, $exception->getMessage());

					throw $exception;
				});
		};
	}
	
	// Collect a request-response pair
	protected function collectRequest($request, $response = null, $startTime = null, $stats = null, $error = null)
	{
		$trace = StackTrace::get();

		$request = (object) [
			'request'  => (object) [
				'method'  => $request->getMethod(),
				'url'     => $this->removeAuthFromUrl((string) $request->getUri()),
				'headers' => $request->getHeaders(),
				'content' => $this->collectContent ? $this->resolveRequestContent($request) : null,
				'body'    => $this->collectRawContent ? (string) $request->getBody() : null
			],
			'response' => $response ? (object) [
				'status'  => (int) $response->getStatusCode(),
				'headers' => $response->getHeaders(),
				'content' => $this->collectContent ? json_decode((string) $response->getBody(), true) : null,
				'body'    => $this->collectRawContent ? (string) $response->getBody() : null
			] : null,
			'stats'    => $stats ? (object) [
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
			] : null,
			'error'    => $error,
			'time'     => $startTime,
			'duration' => (microtime(true) - $startTime) * 1000,
			'trace'    => (new Serializer)->trace($trace)
		];
		
		if ($response->getBody()->tell()) $response->getBody()->rewind();

		if ($this->passesFilters([ $request ])) {
			$this->requests[] = $request;
		}
	}
	
	// Resolve request content, with support for form data and json requests
	protected function resolveRequestContent($request)
	{
		$body = (string) $request->getBody();
		$headers = $request->getHeaders();
		
		if (isset($headers['Content-Type']) && $headers['Content-Type'][0] == 'application/x-www-form-urlencoded') {
			parse_str($body, $parameters);
			return $parameters;
		} elseif (isset($headers['Content-Type']) && strpos($headers['Content-Type'][0], 'json') !== false) {
			return json_decode($body, true);
		}

        return [];
	}
	
	// Removes username and password from the URL
	protected function removeAuthFromUrl($url)
	{
		return preg_replace('#^(.+?://)(.+?@)(.*)$#', '$1$3', $url);
	}
}
