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
    protected $levels = array(
        Logger::DEBUG => ClockworkLog::DEBUG,
        Logger::INFO => ClockworkLog::INFO,
        Logger::NOTICE => ClockworkLog::NOTICE,
        Logger::WARNING => ClockworkLog::WARNING,
        Logger::ERROR => ClockworkLog::ERROR,
        Logger::CRITICAL => ClockworkLog::ERROR,
        Logger::ALERT => ClockworkLog::ERROR,
        Logger::EMERGENCY => ClockworkLog::ERROR,
    );

    public function __construct(ClockworkLog $clockworkLog)
    {
        parent::__construct();

        $this->clockworkLog = $clockworkLog;
    }

    protected function write(array $record)
    {
        $this->clockworkLog->log($record['message'], $this->levels[$record['level']]);
    }
}