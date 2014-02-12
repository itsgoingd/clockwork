<?php
namespace Clockwork\Support\Swift;

use Clockwork\Request\Timeline as ClockworkTimeline;

use Swift_Events_SendEvent;
use Swift_Events_SendListener;

/**
 * Adds records of sent email to the Clockwork timeline
 */
class SwiftPluginClockworkTimeline implements Swift_Events_SendListener
{
    /**
     * Clockwork Timeline data structure
     */
    private $timeline;

    public function __construct(ClockworkTimeline $timeline)
    {
        $this->timeline = $timeline;
    }

    /**
     * Invoked immediately before the Message is sent.
     *
     * @param Swift_Events_SendEvent $evt
     */
    public function beforeSendPerformed(Swift_Events_SendEvent $evt)
    {
        $message = $evt->getMessage();

        $headers = array();
        foreach ($message->getHeaders()->getAll() as $header) {
            $headers[$header->getFieldName()] = $header->getFieldBody();
        }

        $this->timeline->startEvent(
            'email ' . $message->getId(),
            'Sending an email message',
            null,
            array(
                'from'    => $this->addressToString($message->getFrom()),
                'to'      => $this->addressToString($message->getTo()),
                'subject' => $message->getSubject(),
                'headers' => $headers
            )
        );
    }

    /**
     * Invoked immediately after the Message is sent.
     *
     * @param Swift_Events_SendEvent $evt
     */
    public function sendPerformed(Swift_Events_SendEvent $evt)
    {
        $message = $evt->getMessage();

        $this->timeline->endEvent('email ' . $message->getId());
    }

    protected function addressToString($address)
    {
        foreach ($address as $email => $name) {
            if ($name) {
                $address[$email] = "$name <$email>";
            } else {
                $address[$email] = $email;
            }
        }

        return implode(', ', $address);
    }
}
