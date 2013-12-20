<?php
namespace Clockwork\Support\Monolog\Handler;

use Clockwork\Request\Log as ClockworkLog;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * Stores messages to Clockwork Log instance
 */
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