<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\StackTrace;
use Clockwork\Request\Request;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;

// Data source for Laravel notifications and mail components, provides sent notifications and emails
class LaravelNotificationsDataSource extends DataSource
{
	// Event dispatcher instance
	protected $dispatcher;

	// Sent notifications
	protected $notifications = [];

	// Last collected notification
	protected $lastNotification;

	// Create a new data source instance, takes an event dispatcher as argument
	public function __construct(Dispatcher $dispatcher)
	{
		$this->dispatcher = $dispatcher;
	}

	// Add sent notifications to the request
	public function resolve(Request $request)
	{
		$request->notifications = array_merge($request->notifications, $this->notifications);

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->notifications = [];
	}

	// Listen to the email and notification events
	public function listenToEvents()
	{
		$this->dispatcher->listen(MessageSending::class, function ($event) { $this->sendingMessage($event); });
		$this->dispatcher->listen(MessageSent::class, function ($event) { $this->sentMessage($event); });

		$this->dispatcher->listen(NotificationSending::class, function ($event) { $this->sendingNotification($event); });
		$this->dispatcher->listen(NotificationSent::class, function ($event) { $this->sentNotification($event); });
	}

	// Collect a sent email
	protected function sendingMessage($event)
	{
		$trace = StackTrace::get()->resolveViewName();

		$mailable = ($frame = $trace->first(function ($frame) { return is_subclass_of($frame->object, Mailable::class); }))
			? $frame->object : null;

		$notification = (object) [
			'subject' => $event->message->getSubject(),
			'from'    => $this->messageAddressToString($event->message->getFrom()),
			'to'      => $this->messageAddressToString($event->message->getTo()),
			'content' => $this->messageBody($event->message),
			'type'    => 'mail',
			'data'    => [
				'cc'       => $this->messageAddressToString($event->message->getCc()),
				'bcc'      => $this->messageAddressToString($event->message->getBcc()),
				'replyTo'  => $this->messageAddressToString($event->message->getReplyTo()),
				'mailable' => (new Serializer)->normalize($mailable)
			],
			'time'    => microtime(true),
			'trace'   => (new Serializer)->trace($trace)
		];

		if ($this->updateLastNotification($notification)) return;

		if ($this->passesFilters([ $notification ])) {
			$this->notifications[] = $this->lastNotification = $notification;
		} else {
			$this->lastNotification = null;
		}
	}

	// Update last notification with time taken to send it
	protected function sentMessage($event)
	{
		if ($this->lastNotification) {
			$this->lastNotification->duration = (microtime(true) - $this->lastNotification->time) * 1000;
		}
	}

	// Collect a sent notification
	protected function sendingNotification($event)
	{
		$trace = StackTrace::get()->resolveViewName();

		$channelSpecific = $this->resolveChannelSpecific($event);

		$notification = (object) [
			'subject' => $channelSpecific['subject'],
			'from'    => $channelSpecific['from'],
			'to'      => $channelSpecific['to'],
			'content' => $channelSpecific['content'],
			'type'    => $event->channel,
			'data'    => array_merge($channelSpecific['data'], [
				'notification' => (new Serializer)->normalize($event->notification),
				'notifiable'   => (new Serializer)->normalize($event->notifiable)
			]),
			'time'    => microtime(true),
			'trace'   => (new Serializer)->trace($trace)
		];

		if ($this->passesFilters([ $notification ])) {
			$this->notifications[] = $this->lastNotification = $notification;
		} else {
			$this->lastNotification = null;
		}
	}

	// Update last notification with time taken to send it and response
	protected function sentNotification($event)
	{
		if ($this->lastNotification) {
			$this->lastNotification->duration = (microtime(true) - $this->lastNotification->time) * 1000;
			$this->lastNotification->data['response'] = $event->response;
		}
	}

	// Update last sent email notification with additional data from the message sent event
	protected function updateLastNotification($notification)
	{
		if (! $this->lastNotification) return false;

		if ($this->lastNotification->to !== $notification->to) return false;

		$this->lastNotification->subject = $notification->subject;
		$this->lastNotification->from    = $notification->from;
		$this->lastNotification->to      = $notification->to;
		$this->lastNotification->content = $notification->content;

		$this->lastNotification->data = array_merge($this->lastNotification->data, $notification->data);

		return true;
	}

	// Resolve notification channel specific data
	protected function resolveChannelSpecific($event)
	{
		if (method_exists($event->notification, 'toMail')) {
			$channelSpecific = $this->resolveMailChannelSpecific($event, $event->notification->toMail($event->notifiable));
		} elseif (method_exists($event->notification, 'toSlack')) {
			$channelSpecific = $this->resolveSlackChannelSpecific($event, $event->notification->toSlack($event->notifiable));
		} elseif (method_exists($event->notification, 'toNexmo')) {
			$channelSpecific = $this->resolveNexmoChannelSpecific($event, $event->notification->toNexmo($event->notifiable));
		} elseif (method_exists($event->notification, 'toBroadcast')) {
			$channelSpecific = [ 'data' => [ 'data' => (new Serializer)->normalize($event->notification->toBroadcast($event->notifiable)) ] ];
		} elseif (method_exists($event->notification, 'toArray')) {
			$channelSpecific = [ 'data' => [ 'data' => (new Serializer)->normalize($event->notification->toArray($event->notifiable)) ] ];
		} else {
			$channelSpecific = [];
		}

		return array_merge(
			[ 'subject' => null, 'from' => null, 'to' => null, 'content' => null, 'data' => [] ], $channelSpecific
		);
	}

	// Resolve mail notification channel specific data
	protected function resolveMailChannelSpecific($event, $message)
	{
		return [
			'subject' => $message->subject ?: get_class($event->notification),
			'from'    => $this->notificationAddressToString($message->from),
			'to'      => $this->notificationAddressToString($event->notifiable->routeNotificationFor('mail', $event->notification)),
			'data'    => [
				'cc'      => $this->notificationAddressToString($message->cc),
				'bcc'     => $this->notificationAddressToString($message->bcc),
				'replyTo' => $this->notificationAddressToString($message->replyTo)
			]
		];
	}

	// Resolve Slack notification channel specific data
	protected function resolveSlackChannelSpecific($event, $message)
	{
		return [
			'subject' => get_class($event->notification),
			'from'    => $message->username,
			'to'      => $message->channel,
			'content' => $message->content
		];
	}

	// Resolve Nexmo notification channel specific data
	protected function resolveNexmoChannelSpecific($event, $message)
	{
		return [
			'subject' => get_class($event->notification),
			'from'    => $message->from,
			'to'      => $event->notifiable->routeNotificationFor('nexmo', $event->notification),
			'content' => $message->content
		];
	}

	protected function messageAddressToString($address)
	{
		if (! $address) return;

		return array_map(function ($address, $key) {
			// Laravel 8 or earlier
			if (! ($address instanceof \Symfony\Component\Mime\Address)) {
				return $address ? "{$address} <{$key}>" : $key;
			}

			// Laravel 9 or later
			return $address->toString();
		}, $address, array_keys($address));
	}

	protected function messageBody($message)
	{
		// Laravel 8 or earlier
		if (! ($message instanceof \Symfony\Component\Mime\Email)) {
			return $message->getBody();
		}

		// Laravel 9 or later
		return $message->getHtmlBody() ?: $message->getTextBody();
	}

	protected function notificationAddressToString($address)
	{
		if (! $address) return;
		if (! is_array($address)) $address = [ $address ];

		return array_map(function ($address) {
			if (! is_array($address)) return $address;

			$email = isset($address['address']) ? $address['address'] : $address[0];
			$name = isset($address['name']) ? $address['name'] : $address[1];

			return $name ? "{$name} <{$email}>" : $email;
		}, $address);
	}
}
