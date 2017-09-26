<?php namespace Clockwork\Support\Laravel;

use Illuminate\Console\Command;

use Symfony\Component\Console\Input\InputOption;

class ClockworkCleanCommand extends Command
{
	// The console command name.
	protected $name = 'clockwork:clean';

	// The console command description.
	protected $description = 'Cleans Clockwork request metadata';

	public function getOptions()
	{
		return [
			[ 'all', 'a', InputOption::VALUE_NONE, 'cleans all data' ],
			[ 'expiration', 'e', InputOption::VALUE_REQUIRED, 'cleans data older then specified value in seconds' ]
		];
	}

	// Execute the console command.
	public function handle()
	{
		if ($this->option('all')) {
			$this->laravel['config']->set('clockwork.storage_expiration', 0);
		} elseif ($expiration = $this->option('expiration')) {
			$this->laravel['config']->set('clockwork.storage_expiration', $expiration);
		}

		$this->laravel['clockwork.support']->getStorage()->cleanup($force = true);

		$this->info('Metadata cleaned successfully.');
	}

	// Compatibility for old Laravel versions
    public function fire()
    {
        return $this->handle();
    }
}
