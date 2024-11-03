<?php namespace Clockwork\Support\Slim\Legacy;

use Clockwork\Clockwork;
use Clockwork\Authentication\NullAuthenticator;
use Clockwork\DataSource\PsrMessageDataSource;
use Clockwork\Storage\FileStorage;
use Clockwork\Helpers\ServerTiming;

use Psr\Http\Message\{ResponseInterface as Response, ServerRequestInterface as Request};

// Slim 3 middleware
class ClockworkMiddleware
{
	protected $clockwork;
	protected $startTime;

	public function __construct($storagePathOrClockwork, $startTime = null)
	{
		$this->clockwork = $storagePathOrClockwork instanceof Clockwork
			? $storagePathOrClockwork : $this->createDefaultClockwork($storagePathOrClockwork);
		$this->startTime = $startTime ?: microtime(true);
	}

	public function __invoke(Request $request, Response $response, callable $next)
	{
		return $this->process($request, $response, $next);
	}

	public function process(Request $request, Response $response, callable $next)
	{
		$authUri = '#/__clockwork/auth#';
		if (preg_match($authUri, $request->getUri()->getPath(), $matches)) {
			return $this->authenticate($response, $request);
		}

		$clockworkDataUri = '#/__clockwork(?:/(?<id>([0-9-]+|latest)))?(?:/(?<direction>(?:previous|next)))?(?:/(?<count>\d+))?#';
		if (preg_match($clockworkDataUri, $request->getUri()->getPath(), $matches)) {
			$matches = array_merge([ 'id' => null, 'direction' => null, 'count' => null ], $matches);
			return $this->retrieveRequest($response, $request, $matches['id'], $matches['direction'], $matches['count']);
		}

		$response = $next($request, $response);

		return $this->logRequest($request, $response);
	}

	protected function authenticate(Response $response, Request $request)
	{
		$token = $this->clockwork->authenticator()->attempt($request->getParsedBody());

		return $response->withJson([ 'token' => $token ])->withStatus($token ? 200 : 403);
	}

	protected function retrieveRequest(Response $response, Request $request, $id, $direction, $count)
	{
		$authenticator = $this->clockwork->authenticator();
		$storage = $this->clockwork->storage();

		$authenticated = $authenticator->check(current($request->getHeader('X-Clockwork-Auth')));

		if ($authenticated !== true) {
			return $response
				->withJson([ 'message' => $authenticated, 'requires' => $authenticator->requires() ])
				->withStatus(403);
		}

		if ($direction == 'previous') {
			$data = $storage->previous($id, $count);
		} elseif ($direction == 'next') {
			$data = $storage->next($id, $count);
		} elseif ($id == 'latest') {
			$data = $storage->latest();
		} else {
			$data = $storage->find($id);
		}

		return $response
			->withHeader('Content-Type', 'application/json')
			->withJson($data);
	}

	protected function logRequest(Request $request, Response $response)
	{
		$this->clockwork->timeline()->finalize($this->startTime);
		$this->clockwork->addDataSource(new PsrMessageDataSource($request, $response));

		$this->clockwork->resolveRequest();
		$this->clockwork->storeRequest();

		$clockworkRequest = $this->clockwork->request();

		$response = $response
			->withHeader('X-Clockwork-Id', $clockworkRequest->id)
			->withHeader('X-Clockwork-Version', Clockwork::VERSION);

		if ($basePath = $request->getUri()->getBasePath()) {
			$response = $response->withHeader('X-Clockwork-Path', $basePath);
		}

		return $response->withHeader('Server-Timing', ServerTiming::fromRequest($clockworkRequest)->value());
	}

	protected function createDefaultClockwork($storagePath)
	{
		$clockwork = new Clockwork();

		$clockwork->storage(new FileStorage($storagePath));
		$clockwork->authenticator(new NullAuthenticator);

		return $clockwork;
	}
}
