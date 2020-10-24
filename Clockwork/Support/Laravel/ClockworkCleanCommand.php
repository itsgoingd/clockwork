<?php namespace Clockwork\Support\Laravel;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

// Console command for cleaning old requests metadata
class ClockworkCleanCommand extends Command
{
	// Command name
	protected $name = 'clockwork:clean';

	// Command description
	protected $description = 'Cleans Clockwork request metadata';

	// Command options
	public function getOptions()
	{
		return [
			[ 'all', 'a', InputOption::VALUE_NONE, 'cleans all data' ],
			[ 'expiration', 'e', InputOption::VALUE_REQUIRED, 'cleans data older than specified value in minutes' ]
		];
	}

	// Execute the console command
	public function handle()
	{
		if ($this->option('all')) {
			$this->laravel['config']->set('clockwork.storage_expiration', 0);
		} elseif ($expiration = $this->option('expiration')) {
			$this->laravel['config']->set('clockwork.storage_expiration', $expiration);
		}

		$this->laravel['clockwork.support']->makeStorage()->cleanup($force = true);

		$this->info('Metadata cleaned successfully.');
	}

	// Compatibility for the old Laravel versions
	public function fire()
	{
		return $this->handle();
	}
}
