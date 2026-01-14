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
    public function handle(RecordsCreateService $service): int
    {
        $this->output->write("\033c");

        $this->info('🚀 Starting records initialization...');
        $this->line('..................................................');

        $this->info('▶ INIT RECORDS');
        $service->initRecords();
        $this->info('✅ Init Records checked');
        $this->line('..................................................');

        $this->info('🏁 Records created!');

        return self::SUCCESS;
    }
}
