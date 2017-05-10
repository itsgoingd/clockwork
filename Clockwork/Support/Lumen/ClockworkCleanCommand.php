<?php namespace Clockwork\Support\Lumen;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class ClockworkCleanCommand extends Command
{
	/**
	 * The console command name.
	 */
	protected $name = 'clockwork:clean';

	/**
	 * The console command description.
	 */
	protected $description = 'Cleans all request metadata';

	public function getOptions()
	{
		return [
			[ 'age', 'a', InputOption::VALUE_OPTIONAL, 'delete data about requests older then specified time in hours', null ],
		];
	}

	/**
	 * Execute the console command.
	 */
	public function fire()
	{
		$dataDir = storage_path() . '/clockwork';

		$this->info("Cleaning {$dataDir}...");

		$files = glob("{$dataDir}/*.json");

		if (! $files || ! count($files)) {
			$this->info('Nothing to clean up.');
			return;
		}

		$maxAge = $this->option('age') ? time() - $this->option('age') * 60 * 60 : null;

		$count = 0;
		foreach ($files as $file) {
			$tokens = explode('.', basename($file));

			if ($maxAge && $tokens[0] > $maxAge) {
				continue;
			}

			unlink($file);
			$count++;
		}

		$this->info("{$count} files removed.");
	}
}
