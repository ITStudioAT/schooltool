<?php

namespace App\Console\Commands;

use App\Services\InstallUpdateService;
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
    public function handle(InstallUpdateService $service)
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
        $this->info('▶ ROLES AND USERS');
        $service->createRoles(['super_admin', 'admin']);
        $this->info('✅ Roles checked');
        $service->checkSuperAdmins();
        $this->info('✅ Super-Admins checked');


        $this->line('..................................................');

        // END 
        $this->info('🏁 Application update finished!');
    }
}
