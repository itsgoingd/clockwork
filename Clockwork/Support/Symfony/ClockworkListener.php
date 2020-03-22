<?php namespace Clockwork\Support\Symfony;

use Clockwork\Clockwork;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ClockworkListener implements EventSubscriberInterface
{
	protected $clockwork;
	protected $profiler;

	public function __construct(ClockworkSupport $clockwork, Profiler $profiler)
	{
		$this->clockwork = $clockwork;
		$this->profiler = $profiler;
	}

	public function onKernelRequest(KernelEvent $event)
	{
		if (preg_match('#/__clockwork(.*)#', $event->getRequest()->getPathInfo())) {
			$this->profiler->disable();
		}
	}

	public function onKernelResponse(KernelEvent $event)
	{
		if (! $this->clockwork->isEnabled()) return;

		$response = $event->getResponse();

		if (! $response->headers->has('X-Debug-Token')) return;

		$response->headers->set('X-Clockwork-Id', $response->headers->get('X-Debug-Token'));
		$response->headers->set('X-Clockwork-Version', Clockwork::VERSION);
	}

	public static function getSubscribedEvents()
	{
		return [
			KernelEvents::REQUEST => [ 'onKernelRequest', 512 ],
			KernelEvents::RESPONSE => [ 'onKernelResponse', -128 ]
		];
	}
}
