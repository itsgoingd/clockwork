<?php namespace Clockwork\Support\Symfony;

use Clockwork\Clockwork;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ClockworkListener implements EventSubscriberInterface
{
	public function onKernelResponse(FilterResponseEvent $event)
	{
		$response = $event->getResponse();
		$request = $event->getRequest();

		if ($response->headers->has('X-Debug-Token')) {
			$response->headers->set('X-Clockwork-Id', $response->headers->get('X-Debug-Token'));
			$response->headers->set('X-Clockwork-Version', Clockwork::VERSION);
		}
	}

	public static function getSubscribedEvents()
	{
		return [
			KernelEvents::RESPONSE => [ 'onKernelResponse', -128 ],
		];
	}
}
