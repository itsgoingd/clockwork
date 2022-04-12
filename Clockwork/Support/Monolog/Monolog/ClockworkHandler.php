<?php namespace Clockwork\Support\Monolog\Monolog;

use Clockwork\Request\Log as ClockworkLog;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

// Stores messages in a Clockwork log instance (compatible with Monolog 1.x)
class ClockworkHandler extends AbstractProcessingHandler
{
	protected $clockworkLog;

	public function __construct(ClockworkLog $clockworkLog)
	{
		parent::__construct();

		$this->clockworkLog = $clockworkLog;
	}

	protected function write(array $record)
	{
		$this->clockworkLog->log($record['level'], $record['message']);
	}
}
