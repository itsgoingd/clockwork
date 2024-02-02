<?php

declare(strict_types=1);

namespace Clockwork\Support\Psr;

use Clockwork\Clockwork;
use Clockwork\DataSource\PsrMessageDataSource;
use Clockwork\Request\IncomingRequest;
use Clockwork\Storage\Search;
use Clockwork\Support\Vanilla\Clockwork as VanillaClockwork;
use Clockwork\Web\Web;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * middleware based on PHP's PSR specifications
 *
 * Many frameworks are built on the PSR specifications, which makes
 * this implementation interoperable with them.
 *
 * TODO:
 * - It would be preferable to detach the `VanillaClockwork` from its
 *   contained `Clockwork` instance. The former is currently only used
 *   to access the configuration.
 *
 * References:
 * - https://www.php-fig.org/psr/psr-7/ -- request/response interfaces
 * - https://www.php-fig.org/psr/psr-15/ -- middleware interface
 * - https://www.php-fig.org/psr/psr-17/ -- response factory interface
 */
class Middleware implements MiddlewareInterface
{
	private VanillaClockwork $clockwork;
	private ResponseFactoryInterface $responseFactory;
	private StreamFactoryInterface $streamFactory;

	public function __construct(
		VanillaClockwork $clockwork,
		ResponseFactoryInterface $responseFactory,
		StreamFactoryInterface $streamFactory
	) {
		$this->clockwork = $clockwork;
		$this->responseFactory = $responseFactory;
		$this->streamFactory = $streamFactory;
	}

	/**
	 * this methods implements the PSR-15 MiddlewareInterface
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		if (!$this->clockwork->isEnabled()) {
			return $handler->handle($request);
		}

		if ($this->clockwork->isWebEnabled() && $this->isWebRequest($request)) {
			$response = $this->handleWebRequest($request);
		} elseif ($this->isApiRequest($request)) {
			$response = $this->handleApiRequest($request);
		} else {
			// Inject clockwork instance into the request, useful to log new events or timeline
			$response = $handler->handle(
				$request->withAttribute('Clockwork', $this->clockwork->getClockwork())
			);

			$uri = $request->getUri();
			parse_str($uri->getQuery(), $input);
			$rx = new IncomingRequest([
				'method'  => $request->getMethod(),
				'uri'     => $uri->__toString(),
				'input'   => $input,
				'cookies' => $request->getCookieParams(),
			]);

			if (!$this->clockwork->getClockwork()->shouldCollect()->filter($rx)) {
				return $response;
			}
			if (!$this->clockwork->getClockwork()->shouldRecord()->filter($this->clockwork->request())) {
				return $response;
			}

			$this->clockwork->getClockwork()->addDataSource(new PsrMessageDataSource($request, $response));
			$this->clockwork->getClockwork()->resolveRequest()->storeRequest();
		}

		// enrich response with clockwork data
		$clockworkRequest = $this->clockwork->request();
		$response = $response->withHeader('X-Clockwork-Id', $clockworkRequest->id);
		$response = $response->withHeader('X-Clockwork-Version', Clockwork::VERSION);
		$response = $response->withHeader('X-Clockwork-Path', $this->clockwork->getApiPath());

		// TODO: reactivate/reimplement
		// foreach ($this->config['headers'] as $headerName => $headerValue) {
		// 	$this->setHeader("X-Clockwork-Header-{$headerName}", $headerValue);
		// }

		// TODO: reactivate/reimplement
		// if ($this->config['features']['performance']['client_metrics'] || $this->config['toolbar']) {
		// 	$this->setCookie('x-clockwork', $this->getCookiePayload(), time() + 60);
		// }

		// TODO: reactivate/reimplement
		// if (($eventsCount = $this->config['server_timing']) !== false) {
		// 	$this->setHeader('Server-Timing', ServerTiming::fromRequest($this->clockwork->request(), $eventsCount)->value());
		// }

		return $response;
	}

	private function isWebRequest(ServerRequestInterface $request): bool
	{
		$requestPath = $request->getUri()->getPath();
		$webPath = $this->clockwork->getWebPath();
		// handle "/web" case
		if ($requestPath === $webPath) {
			return true;
		}
		// Handle other "/web/something" cases. Note that we don't want
		// e.g. "/webcam".
		return str_starts_with($requestPath, $webPath . '/');
	}

	private function isApiRequest(ServerRequestInterface $request): bool
	{
		$requestPath = $request->getUri()->getPath();
		$apiPath = rtrim($this->clockwork->getApiPath(), '/');
		return str_starts_with($requestPath, $apiPath . '/');
	}

	private function handleWebRequest(ServerRequestInterface $request): ResponseInterface
	{
		$requestPath = $request->getUri()->getPath();
		$webPath = $this->clockwork->getWebPath();

		$relativePath = ltrim(substr($requestPath, strlen($webPath)), '/');

		// handle "/web" and "/web/" cases
		if ($relativePath === '') {
			return $this->responseFactory->createResponse(302)
				->withHeader(
					'location',
					$request->getUri()->withPath($webPath . '/index.html')->__toString()
				);
		}

		$web = new Web();
		$asset = $web->asset($relativePath);
		if ($asset === null) {
			return $this->responseFactory->createResponse(404);
		}
		return $this->responseFactory->createResponse()
			->withAddedHeader('Content-type', $asset['mime'])
			->withBody($this->streamFactory->createStreamFromFile($asset['path']));
	}

	private function handleApiRequest(ServerRequestInterface $request): ResponseInterface
	{
		$apiPath = rtrim($this->clockwork->getApiPath(), '/');

		// auth API is a single endpoint
		// Note that this must be handled before checking authentication,
		// lest we end up with a circular dependency.
		$requestPath = $request->getUri()->getPath();
		if ($requestPath === $apiPath . '/auth') {
			$username = $request->getHeader('username')[0] ?? '';
			$password = $request->getHeader('password')[0] ?? '';

			$token = $this->clockwork->getClockwork()->authenticator()->attempt([
				'username' => $username,
				'password' => $password,
			]);

			return $this->responseFactory->createResponse($token ? 200 : 403)
				->withAddedHeader('Content-type', 'application/json')
				->withBody($this->streamFactory->createStream(
					json_encode(
						['token' => $token],
						JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
					)
				));
		}

		// check request authentication
		$authHeaders = $request->getHeader('HTTP_X_CLOCKWORK_AUTH');
		$authenticator = $this->clockwork->getClockwork()->authenticator();
		if (!$authenticator->check($authHeaders[0] ?? '')) {
			return $this->responseFactory->createResponse(403)
				->withAddedHeader('Content-type', 'application/json')
				->withBody($this->streamFactory->createStream(
					json_encode(
						[ 'requires' => $authenticator->requires() ],
						JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
					)
				));
		}

		// load metadata
		if (!preg_match(
			"#$apiPath/(?<id>[0-9-]+|latest)(?:/(?<direction>next|previous))?(?:/(?<count>\d+))?#",
			$request->getUri()->__toString(),
			$matches
		)) {
			return $this->responseFactory->createResponse(404);
		}
		$id = isset($matches['id']) ? $matches['id'] : null;
		$direction = isset($matches['direction']) ? $matches['direction'] : null;
		$count = isset($matches['count']) ? $matches['count'] : null;
		$storage = $this->clockwork->getClockwork()->storage();
		if ($direction == 'previous') {
			$data = $storage->previous($id, $count, Search::fromRequest($_GET));
		} elseif ($direction == 'next') {
			$data = $storage->next($id, $count, Search::fromRequest($_GET));
		} elseif ($id == 'latest') {
			$data = $storage->latest(Search::fromRequest($_GET));
		} else {
			$data = $storage->find($id);
		}
		if ($data === null) {
			return $this->responseFactory->createResponse(404);
		}

		// load extended metadata if requested
		if (preg_match("#$apiPath/(?<id>[0-9-]+|latest)/extended#", $request->getUri()->__toString())) {
			$this->clockwork->getClockwork()->extendRequest($data);
		}

		$body = is_array($data)
			? array_map(static function ($item) { return $item->toArray(); }, $data)
			: $data->toArray();
		return $this->responseFactory->createResponse()
			->withAddedHeader('Content-type', 'application/json')
			->withBody($this->streamFactory->createStream(
				json_encode(
					$body,
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				)
			));
	}
}
