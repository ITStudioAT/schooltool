<?php

namespace Itstudioat\Spa\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class SpaComplete extends Command
{
    protected $signature = 'spa:complete';

    protected $description = 'Installiere JS-Abhängigkeiten für spa';

    public function handle()
    {

        // Run migrations
        $this->call('migrate');
        $this->info('✅ Migrations executed.');
        $this->newLine();

        // Publish all necessary
        $this->call('vendor:publish', [
            '--tag' => 'spa-config',
            '--force' => 'true',
        ]);

        // Publish all necessary
        $this->call('vendor:publish', [
            '--tag' => 'spa-views',
            '--force' => 'true',
        ]);

        // Publish all necessary
        $this->call('vendor:publish', [
            '--tag' => 'spa-all',
            '--force' => 'true',
        ]);

        $this->info('✅ All Laravel-Spa-Files are published.');
        $this->newLine();

        // Spatie Permission
        $this->call('vendor:publish', [
            '--provider' => 'Spatie\Permission\PermissionServiceProvider',
        ]);
        $this->call('optimize:clear');
        $this->call('migrate');
        $this->info('✅ Spatie Permissions published and migrated.');
        $this->newLine();

        // Laravel Sanctum
        /*
        $this->info('Installing Sanctum via Composer...');
        $result = Process::timeout(300)->run(['composer', 'require', 'laravel/sanctum:^4.0']);
        */
        // $this->call('api:install');
        $this->call('vendor:publish', [
            '--provider' => 'Laravel\\Sanctum\\SanctumServiceProvider',
        ]);
        $this->call('config:publish', [
            'name' => 'cors',
        ]);

        $this->call('migrate');
        $this->info('✅ Laravel Sanctum installed.');
        $this->newLine();

        // package.json installations
        $this->call('spa:packages');
        $this->info('Running npm install...');

        Process::timeout(300)->run('npm install', function (string $type, string $output) {
            echo $output;
        });
        $this->info('✅ npm install finished.');
        $this->newLine();

        // Create a user
        $this->call('user:create');
        $this->info('✅ User created');
        $this->newLine();

        // Run updates
        $this->call('spa:update');
        $this->info('✅ Update finished');
        $this->newLine();

        // storage:link
        $this->call('storage:link');
        $this->info('✅ Storage linked');
        $this->newLine();

        $this->warn("⚠️  Dont forget to set 'supports_credentials' => true in config/cors.php");
        $this->newLine();

        $this->info('✅ Installation complete!');
    }
}
