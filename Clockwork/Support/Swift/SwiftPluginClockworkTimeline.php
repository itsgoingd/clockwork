<?php namespace Clockwork\Support\Swift;

use Clockwork\Request\Timeline\Timeline as ClockworkTimeline;

use Swift_Events_SendEvent;
use Swift_Events_SendListener;

// Adds records of sent email to the Clockwork timeline
class SwiftPluginClockworkTimeline implements Swift_Events_SendListener
{
	// Clockwork timeline instance
	protected $timeline;

	public function __construct(ClockworkTimeline $timeline)
	{
		$this->timeline = $timeline;
	}

	// Invoked immediately before a message is sent
	public function beforeSendPerformed(Swift_Events_SendEvent $evt)
	{
		$message = $evt->getMessage();

		$headers = [];
		foreach ($message->getHeaders()->getAll() as $header) {
			$headers[$header->getFieldName()] = $header->getFieldBody();
		}

		$this->timeline->event('Sending an email message', [
			'name'  => 'email ' . $message->getId(),
			'start' => $time = microtime(true),
			'data'  => [
				'from'    => $this->addressToString($message->getFrom()),
				'to'      => $this->addressToString($message->getTo()),
				'subject' => $message->getSubject(),
				'headers' => $headers
			]
		]);
	}

	// Invoked immediately after a message is sent
	public function sendPerformed(Swift_Events_SendEvent $evt)
	{
		$message = $evt->getMessage();

		$this->timeline->event('email ' . $message->getId())->end();
	}

	protected function addressToString($address)
	{
		if (! $address) return;

		foreach ($address as $email => $name) {
			$address[$email] = $name ? "$name <$email>" : $email;
		}

		return implode(', ', $address);
	}
}
