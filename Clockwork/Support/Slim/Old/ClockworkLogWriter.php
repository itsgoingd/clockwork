<?php namespace Clockwork\Support\Slim\Old;

use Clockwork\Clockwork;

use Slim\Middleware;

class ClockworkLogWriter
{
	protected $clockwork;
	protected $originalLogWriter;

	protected $logLevels = [
		1 => 'emergency',
		2 => 'alert',
		3 => 'critical',
		4 => 'error',
		5 => 'warning',
		6 => 'notice',
		7 => 'info',
		8 => 'debug'
	];

	public function __construct(Clockwork $clockwork, $originalLogWriter)
	{
		$this->clockwork = $clockwork;
		$this->originalLogWriter = $originalLogWriter;
	}

	public function write($message, $level = null)
	{
		$this->clockwork->log($this->getPsrLevel($level), $message);

		if ($this->originalLogWriter) {
			return $this->originalLogWriter->write($message, $level);
		}
	}

	protected function getPsrLevel($level)
	{
		return $this->logLevels[$level] ?? $level;
	}
}
