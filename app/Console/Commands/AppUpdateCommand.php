<?php

namespace App\Console\Commands;

use App\Services\InstallUpdateService;
use App\Services\RecordsCreateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class AppUpdateCommand extends Command
{
    protected $signature = 'app:update';
    protected $description = 'Update application: migrations, records, roles, folders, and build assets';

    public function handle(InstallUpdateService $service, RecordsCreateService $recordsCreateService)
    {
        // CLEAR CONSOLE
        $this->output->write("\033c");

        $this->info('🚀 Starting application update...');
        $this->line(str_repeat('.', 50));

        // ✅ 1. Run migrations
        $this->info('▶ MIGRATIONS');
        Artisan::call('migrate', ['--force' => true]);
        $this->line(Artisan::output());
        $this->line(str_repeat('.', 50));

        // ✅ 2. Roles and records
        $this->info('▶ ROLES AND RECORDS');
        $service->createRoles(['super_admin', 'admin', 'register_admin', 'register_user']);
        $this->info('✅ Roles checked');

        $recordsCreateService->initRecords();
        $this->info('✅ Init Records checked');
        $this->line(str_repeat('.', 50));

        // ✅ 3. Folders
        $this->info('▶ FOLDERS');
        $service->findOrCreateFolders();
        $this->info('✅ Folders checked');
        $this->line(str_repeat('.', 50));

        // ✅ 4. Frontend build (optional, if Node is available)
        if (file_exists(base_path('package.json'))) {
            $this->info('▶ BUILDING FRONTEND (npm run build)...');

            // detect OS
            $isWindows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';

            if ($isWindows) {
                // on Windows we usually just call npm.cmd directly
                $bashCommand = 'npm run build';
            } else {
                // your original Linux/macOS command with nvm
                $bashCommand = 'export NVM_DIR="$HOME/.nvm" && '
                    . '[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh" && '
                    . 'nvm use 22 && '
                    . 'npm run build';
            }


            $process = Process::fromShellCommandline($bashCommand, base_path());
            $process->setTimeout(600); // 10 minutes max
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });

            if ($process->isSuccessful()) {
                $this->info('✅ Frontend build completed');
            } else {
                $this->error('❌ Frontend build failed');
                $this->error($process->getErrorOutput());
            }
        } else {
            $this->warn('⚠️ No package.json found, skipping frontend build.');
        }

        $this->line(str_repeat('.', 50));
        $this->info('🏁 Application update finished!');
    }
}
