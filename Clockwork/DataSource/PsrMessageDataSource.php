<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Request;

use Psr\Http\Message\{ResponseInterface as PsrResponse, ServerRequestInterface as PsrRequest};

// Data source providing data obtainable from the PSR-7 request and response interfaces
class PsrMessageDataSource extends DataSource
{
	// PSR Messages
	protected $psrRequest;
	protected $psrResponse;

	// Create a new data source, takes PSR-7 request and response as arguments
	public function __construct(?PsrRequest $psrRequest = null, ?PsrResponse $psrResponse = null)
	{
		$this->psrRequest  = $psrRequest;
		$this->psrResponse = $psrResponse;
	}

	// Adds request and response information to the request
	public function resolve(Request $request)
	{
		if ($this->psrRequest) {
			$request->method   = $this->psrRequest->getMethod();
			$request->uri      = $this->getRequestUri();
			$request->headers  = $this->getRequestHeaders();
			$request->getData  = $this->sanitize($this->psrRequest->getQueryParams());
			$request->postData = $this->sanitize($this->psrRequest->getParsedBody());
			$request->cookies  = $this->sanitize($this->psrRequest->getCookieParams());
			$request->time     = $this->getRequestTime();
		}

		if ($this->psrResponse !== null) {
			$request->responseStatus = $this->psrResponse->getStatusCode();
			$request->responseTime   = $this->getResponseTime();
		}

		return $request;
	}

	// Normalize items in the array and remove passwords
	protected function sanitize($data)
	{
		return is_array($data) ? $this->removePasswords((new Serializer)->normalizeEach($data)) : $data;
	}

	// Get the response time, fetching it from ServerParams
	protected function getRequestTime()
	{
		$env = $this->psrRequest->getServerParams();

		return $env['REQUEST_TIME_FLOAT'] ?? null;
	}

	// Get the response time (current time, assuming most of the application code has already run at this point)
	protected function getResponseTime()
	{
		return microtime(true);
	}

	// Get the request headers
	protected function getRequestHeaders()
	{
		$headers = [];

		foreach ($this->psrRequest->getHeaders() as $header => $values) {
			if (strtoupper(substr($header, 0, 5)) === 'HTTP_') {
				$header = substr($header, 5);
			}

			$header = str_replace('_', ' ', $header);
			$header = ucwords(strtolower($header));
			$header = str_replace(' ', '-', $header);

			$headers[$header] = $values;
		}

		ksort($headers);

		return $headers;
	}

	// Get the request URI
	protected function getRequestUri()
	{
		$uri = $this->psrRequest->getUri();

		return $uri->getPath() . ($uri->getQuery() ? '?' . $uri->getQuery() : '');
	}
}
