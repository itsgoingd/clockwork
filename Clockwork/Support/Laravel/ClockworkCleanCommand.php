<?php
namespace Clockwork\Support\Laravel;

use Illuminate\Console\Command;

class ClockworkCleanCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'clockwork:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleans all request metadata';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $data_dir = storage_path() . '/clockwork';

        $this->info('Cleaning ' . $data_dir . ' ...');

        $files = glob($data_dir . '/*.json');

        if (!$files || !count($files)) {
            $this->info('Nothing to clean up.');
            return;
        }

        $count = 0;
        foreach ($files as $file) {
            unlink($file);
            $count++;
        }

        $this->info($count . ' files removed.');
    }
}
