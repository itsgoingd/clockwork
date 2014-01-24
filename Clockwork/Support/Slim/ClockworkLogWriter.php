<?php
namespace Clockwork\Support\Slim;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\SlimDataSource;
use Clockwork\Storage\FileStorage;

use Slim\Middleware;

class ClockworkLogWriter
{
	protected $clockwork;
	protected $original_log_writer;

	protected $log_levels = array(
		1 => 'emergency',
		2 => 'alert',
		3 => 'critical',
		4 => 'error',
		5 => 'warning',
		6 => 'notice',
		7 => 'info',
		8 => 'debug'
	);

	public function __construct(Clockwork $clockwork, $original_log_writer)
	{
		$this->clockwork = $clockwork;
		$this->original_log_writer = $original_log_writer;
	}

	public function write($message, $level = null)
	{
		$this->clockwork->log($this->getPsrLevel($level), $message);

		if ($this->original_log_writer) {
			return $this->original_log_writer->write($message, $level);
		}
	}

	protected function getPsrLevel($level)
	{
		return isset($this->log_levels[$level]) ? $this->log_levels[$level] : $level;
	}
}
