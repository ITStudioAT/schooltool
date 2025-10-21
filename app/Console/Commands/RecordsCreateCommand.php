<?php

namespace App\Console\Commands;

use App\Services\RecordsCreateService;
use Illuminate\Console\Command;

class RecordsCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'records:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Records in some tables';

    /**
     * Execute the console command.
     */
    public function handle(RecordsCreateService $service)
    {
        // CLEAR CONSOLE
        $this->output->write("\033c");

        // START 
        $this->info('🚀 Starting application update...');
        $this->line('..................................................');

        // ROLES
        $this->info('▶ INIT RECORDS');
        $service->initRecords();
        $this->info('✅ Init Records checked');
        $this->line('..................................................');

        // END 
        $this->info('🏁 Records creates!');
    }
}
