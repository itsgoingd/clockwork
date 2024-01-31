<?php

namespace Clockwork\Support\Mezzio;

use Clockwork\Support\Mezzio\Clockwork;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ClockworkMiddleware implements MiddlewareInterface
{
    protected $clockwork;

    public function __construct(Clockwork $clockwork = null)
    {
        $this->clockwork = $clockwork ?? Clockwork::init();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestPath = rtrim($request->getUri()->getPath(), '/');
        $apiPath = ltrim($this->clockwork->getApiPath(), '/');
        $clockworkDataUri = "#/$apiPath(?:/(?<id>[0-9-]+))?(?:/(?<direction>(?:previous|next)))?(?:/(?<count>\d+))?#";
        if (preg_match($clockworkDataUri, $requestPath, $matches)) {
            return new JsonResponse($this->clockwork->getMetadata($request->getUri()->getPath()));
        }

        if ($this->clockwork->isWebEnabled()) {
            if ($requestPath == $this->clockwork->getWebHost()) {
                return  $this->clockwork->usePsrMessage($request, new Response())->returnWeb();
            }
        }

        return $this->clockwork->usePsrMessage($request, $handler->handle($request))->requestProcessed();
    }
}
