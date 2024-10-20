<?php namespace Clockwork\Support\Monolog\Monolog3;

use Clockwork\Request\Log as ClockworkLog;

use Monolog\{Logger, LogRecord};
use Monolog\Handler\AbstractProcessingHandler;

// Stores messages in a Clockwork log instance (compatible with Monolog 3.x)
class ClockworkHandler extends AbstractProcessingHandler
{
	protected $clockworkLog;

	public function __construct(ClockworkLog $clockworkLog)
	{
		parent::__construct();

		$this->clockworkLog = $clockworkLog;
	}

	protected function write(LogRecord $record): void
	{
		$this->clockworkLog->log($record->level->getName(), $record['message']);
	}
}
