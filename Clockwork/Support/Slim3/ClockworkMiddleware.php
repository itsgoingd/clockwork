<?php namespace Clockwork\Support\Slim3;


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Clockwork\DataSource\PsrMessageDataSource;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Clockwork;

class ClockworkMiddleware {
    private $clockwork;
    private $startTime;
    private $baseUrl;
    
    const DEFAULT_BASE_URL = '/__clockwork/';

	public function __construct(Clockwork $clockwork, $options = [])
	{
		$this->clockwork = $clockwork;
		
		$options = array_merge([
			'baseUrl' => self::DEFAULT_BASE_URL,
			'startTime' => null
		], $options);
		
		$this->baseUrl = $options['baseUrl'];
		$this->startTime = $options['startTime'];
	}
    
    public function __invoke(Request $request, Response $response, callable $next) 
    {
        return $this->process($request, $response, $next);
    }
    
    public function process(Request $request, Response $response, callable $next) 
    {
    	$pathPattern = '#^'.preg_quote(rtrim($this->baseUrl, '/'), '#').'(?:/(?<id>[0-9\.]+))?(?:/(?<direction>(?:previous|next)))?(?:/(?<count>\d+))?#';
    	$matches = null;
    	if(preg_match($pathPattern, $request->getUri()->getPath(), $matches)) {
    		$id = isset($matches['id']) ? $matches['id'] : null;
    		$direction = isset($matches['direction']) ? $matches['direction'] : null;
    		$count = isset($matches['count']) ? intval($matches['count']) : null;
    		return $this->retrieveRequest($id, $direction, $count, $response);
    	}
    	
        $response = $next($request, $response);
        
        return $this->logRequest($request, $response);
    }
    
    private function retrieveRequest($id, $direction, $count, Response $response) 
    {
    	$storage = $this->clockwork->getStorage();
    	
		if ($direction === 'previous') {
			$data = $storage->previous($id, $count);
		} elseif ($direction === 'next') {
			$data = $storage->next($id, $count);
		} elseif ($id === 'latest') {
			$data = $storage->latest();
		} else {
			$data = $storage->find($id);
		}
    	
		return $response
		    ->withHeader('Content-Type', 'application/json')
		    ->withJson($data);
    }
    
    private function logRequest(Request $request, Response $response) 
    {
    	if(is_null($this->clockwork)) {
    		return $response;
    	}
    	
    	$this->clockwork->getTimeline()->finalize($this->startTime);
    	$this->clockwork->addDataSource(new PsrMessageDataSource($request, $response));
    	
    	$this->clockwork->resolveRequest();
    	$this->clockwork->storeRequest();
    	
    	$clockworkRequest = $this->clockwork->getRequest();
    	
    	return $response->withHeader('X-Clockwork-Id', $clockworkRequest->id)
	    				->withHeader('X-Clockwork-Version', Clockwork::VERSION)
				    	->withHeader('X-Clockwork-Path', $request->getUri()->getBasePath() . $this->baseUrl)
				    	->withHeader('Server-Timing', ServerTiming::fromRequest($clockworkRequest)->value());
    }
}