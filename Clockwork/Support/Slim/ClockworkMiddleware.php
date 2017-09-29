<?php namespace Clockwork\Support\Slim;

use Clockwork\Clockwork;
use Clockwork\DataSource\PsrMessageDataSource;
use Clockwork\Storage\FileStorage;
use Clockwork\Helpers\ServerTiming;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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
        $clockworkDataUri = '#/__clockwork(?:/(?<id>[0-9-]+))?(?:/(?<direction>(?:previous|next)))?(?:/(?<count>\d+))?#';
		if (preg_match($clockworkDataUri, $request->getUri()->getPath(), $matches)) {
            $matches = array_merge([ 'direction' => null, 'count' => null ], $matches);
			return $this->retrieveRequest($response, $matches['id'], $matches['direction'], $matches['count']);
		}

        $response = $next($request, $response);

        return $this->logRequest($request, $response);
    }

    protected function retrieveRequest(Response $response, $id, $direction, $count)
    {
    	$storage = $this->clockwork->getStorage();

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
    	$this->clockwork->getTimeline()->finalize($this->startTime);
    	$this->clockwork->addDataSource(new PsrMessageDataSource($request, $response));

    	$this->clockwork->resolveRequest();
    	$this->clockwork->storeRequest();

    	$clockworkRequest = $this->clockwork->getRequest();

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

        $clockwork->setStorage(new FileStorage($storagePath));

        return $clockwork;
    }
}
