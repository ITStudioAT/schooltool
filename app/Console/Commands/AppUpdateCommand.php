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

        // ✅Delete Records in Test-Models
        $this->info('▶ CLEAR TEST-FILES');
        $service->clearModels();
        $this->info('✅ Records in test-files deleted');
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
            $isWindows = PHP_OS_FAMILY === 'Windows';
            $scriptDir = base_path('scripts');
            $posixScript = $scriptDir . DIRECTORY_SEPARATOR . 'build_frontend.sh';
            $winScript   = $scriptDir . DIRECTORY_SEPARATOR . 'build_frontend.cmd';
            if ($isWindows) {
                $this->info('▶ Windows detected');
                $command = 'cmd /C ' . escapeshellarg($winScript);
            } else {
                $this->info('▶ Non-Windows detected');
                $command = 'bash -lc ' . escapeshellarg($posixScript);
            }
            $process = Process::fromShellCommandline($command, base_path());
            $process->setTimeout(900); // 15 minutes
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });
            if ($process->isSuccessful()) {
                $this->info('✅ Frontend build completed');
            } else {
                $this->warn('⚠️ Frontend build failed — see logs above.');
                $this->error($process->getOutput());
                $this->error($process->getErrorOutput());
            }
        } else {
            $this->warn('⚠️ No package.json found, skipping frontend build.');
        }
        $this->line(str_repeat('.', 50));
        // ✅ 5. Clear all caches
        $this->info('▶ CLEARING CACHES');
        Artisan::call('optimize:clear');
        $this->line(Artisan::output());
        $this->info('✅ Caches cleared');
        $this->line(str_repeat('.', 50));
        $this->info('🏁 Application update finished!');
    }
}
