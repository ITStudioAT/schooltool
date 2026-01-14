<?php

namespace App\Console\Commands;

use App\Services\RegisterTestRecordsService;
use Illuminate\Console\Command;

class MakeRegisterTestRecordsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make-test:register';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test records for registration system';

    /**
     * Execute the console command.
     */
    public function handle(RegisterTestRecordsService $service): int
    {
        $this->output->write("\033c");

        $this->info('🚀 Starting Test Setup for Register...');

        $this->info('▶ CHECKING REQUIREMENTS');
        if (! $service->checkRequirement()) {
            $this->error('⚠️ Requirements failed - Command stopped');

            return self::FAILURE;
        }
        $this->info('✅ Requirements checked');

        $this->info('▶ CHECKING USERS');
        if ($service->checkOrCreateUsers()) {
            $this->info('✅ Users created');
        } else {
            $this->info('✅ Users already exists');
        }

        $this->info('▶ CREATING BOOKINGS');
        $service->createRegisterEntries();
        $this->info('✅ Bookings created');

        $this->info('🏁 Command finished!');

        return self::SUCCESS;
    }
}
