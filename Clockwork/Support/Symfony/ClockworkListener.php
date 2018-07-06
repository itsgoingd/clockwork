<?php namespace Clockwork\Support\Symfony;

use Clockwork\Clockwork;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ClockworkListener implements EventSubscriberInterface
{
	protected $clockwork;

	public function __construct(ClockworkSupport $clockwork)
	{
		$this->clockwork = $clockwork;
	}

	public function onKernelResponse(FilterResponseEvent $event)
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
			KernelEvents::RESPONSE => [ 'onKernelResponse', -128 ],
		];
	}
}
