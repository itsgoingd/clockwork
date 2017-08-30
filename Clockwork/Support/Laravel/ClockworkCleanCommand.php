<?php
namespace Clockwork\Support\Laravel;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

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

    public function getOptions()
    {
        return array(
            array('age', 'a', InputOption::VALUE_OPTIONAL, 'delete data about requests older then specified time in hours', null),
        );
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $data_dir = storage_path() . '/clockwork';

        $this->info('Cleaning ' . $data_dir . ' ...');

        $files = glob($data_dir . '/*.json');

        if (!$files || !count($files)) {
            $this->info('Nothing to clean up.');
            return;
        }

        $max_age = ($this->option('age')) ? time() - $this->option('age') * 60 * 60 : null;

        $count = 0;
        foreach ($files as $file) {
            $tokens = explode('.', basename($file));

            if ($max_age && $tokens[0] > $max_age) {
                continue;
            }

            unlink($file);
            $count++;
        }

        $this->info($count . ' files removed.');
    }

    // compatibility for old Laravel versions
    public function fire()
    {
        return $this->handle();
    }
}
