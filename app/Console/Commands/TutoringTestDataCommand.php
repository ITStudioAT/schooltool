<?php

namespace App\Console\Commands;

use Database\Seeders\TutoringTestDataCleanupSeeder;
use Database\Seeders\TutoringTestDataSeeder;
use Illuminate\Console\Command;

class TutoringTestDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tutoring:test-data {action : add, add-small, or remove}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verwaltet Tutoring Test-Daten (add/add-small/remove)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'add':
                $this->info('📝 Füge Tutoring Test-Daten hinzu (100 Schulen, ~100.000 Benutzer)...');
                $this->info('⏱️  Dies dauert ca. 3-5 Minuten...');
                $this->call('db:seed', ['--class' => TutoringTestDataSeeder::class]);
                break;

            case 'add-small':
                $this->info('📝 Füge kleine Test-Daten hinzu (1 Schule, 10 Schüler, 2 Angebote)...');
                $this->call('db:seed', ['--class' => \Database\Seeders\TutoringTestDataSmallSeeder::class]);
                break;

            case 'remove':
                $this->info('🗑️  Entferne Tutoring Test-Daten...');
                $this->call('db:seed', ['--class' => TutoringTestDataCleanupSeeder::class]);
                break;

            default:
                $this->error('❌ Ungültige Aktion. Verwenden Sie "add", "add-small" oder "remove".');
                $this->info('Verwendung:');
                $this->info('  php artisan tutoring:test-data add        - Fügt vollständige Test-Daten hinzu (100 Schulen)');
                $this->info('  php artisan tutoring:test-data add-small  - Fügt kleine Test-Daten hinzu (1 Schule)');
                $this->info('  php artisan tutoring:test-data remove     - Entfernt Test-Daten');
                return 1;
        }

        return 0;
    }
}
