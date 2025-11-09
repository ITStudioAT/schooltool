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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        // CLEAR CONSOLE
        $this->output->write("\033c");

        $this->info('🚀 Starting Test Setup for Register...');
        $service = new RegisterTestRecordsService();


        // Prüfen, ob Voraussetzungen passen (Schule, Schuljahr angelegt)
        $this->info('▶ CHECKING REQUIREMENTS');
        $check = $service->checkRequirement();
        if ($check) {
            $this->info('✅ Requirements checked');
        } else {
            $this->info('⚠️ Requirements failed');
            $this->info('⚠️ Command stopped');
        }


        // Prüfen, ob Users anleget sind, wenn nein => anlegen
        $this->info('▶ CHECKING USERS');
        $check = $service->checkOrCreateUsers();
        if ($check) {
            $this->info('✅ Users created');
        } else {
            $this->info('✅ Users already exists');
        }

        // Buchungen erzeugen
        $this->info('▶ CREATING BOOKINGS');
        $service->createRegisterEntries();
        $this->info('✅ Bookings created');

        // END 
        $this->info('🏁 Command finished!');
    }
}
