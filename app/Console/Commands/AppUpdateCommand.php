<?php

namespace App\Console\Commands;

use App\Services\InstallUpdateService;
use App\Services\RecordsCreateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AppUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(InstallUpdateService $service, RecordsCreateService $recordsCreateService)
    {

        // CLEAR CONSOLE
        $this->output->write("\033c");


        // START 
        $this->info('🚀 Starting application update...');
        $this->line('..................................................');

        // MIGRATIONS
        $this->info('▶ MIGRATIONS');
        Artisan::call('migrate', [
            '--force' => true, // required in non-interactive environments (like production/commands)
        ]);
        $this->line(Artisan::output());
        $this->line('..................................................');

        // ROLES
        $this->info('▶ ROLES AND RECORDS');
        $service->createRoles(['super_admin', 'admin', 'register_admin', 'register_user']);
        $this->info('✅ Roles checked');

        $recordsCreateService->initRecords();
        $this->info('✅ Init Records checked');
        $this->line('..................................................');

        // FOLDERS
        $this->info('▶ FOLDERS');
        $service->findOrCreateFolders();
        $this->info('✅ Folders checked');
        $this->line('..................................................');

        // END 
        $this->info('🏁 Application update finished!');
    }
}
