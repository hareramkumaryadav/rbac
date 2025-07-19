<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeWithQueue extends Command
{
    protected $signature = 'serve:queue';
    protected $description = 'Start Laravel server and queue worker together';

    public function handle()
    {
        $this->info('Starting Laravel server and queue worker...');

        // Start `php artisan serve`
        $serve = new Process(['php', 'artisan', 'serve']);
        $serve->start();

        // Start `php artisan queue:work`
        $queue = new Process(['php', 'artisan', 'queue:work']);
        $queue->start();

        // Stream output
        while ($serve->isRunning() || $queue->isRunning()) {
            if ($output = $serve->getIncrementalOutput()) {
                $this->info(trim($output));
            }
            if ($output = $queue->getIncrementalOutput()) {
                $this->line(trim($output));
            }
            usleep(50000);
        }

        return 0;
    }
}
