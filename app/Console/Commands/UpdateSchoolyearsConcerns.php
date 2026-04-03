<?php

namespace App\Console\Commands;

use App\Models\Schoolyear;
use Illuminate\Console\Command;

class UpdateSchoolyearsConcerns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schoolyears:concerns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract year from schoolyear names and update concerns field';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $schoolyears = Schoolyear::all();
        $updated = 0;

        foreach ($schoolyears as $schoolyear) {
            // Extract year pattern like "2025/26" from name like "Schuljahr 2025/26"
            if (preg_match('/(\d{4}\/\d{2})/', $schoolyear->name, $matches)) {
                $schoolyear->concerns = $matches[1];
                $schoolyear->save();
                $updated++;
                $this->info("Updated: {$schoolyear->name} -> {$matches[1]}");
            }
        }

        $this->info("✓ Updated {$updated} schoolyear(s)");

        return Command::SUCCESS;
    }
}
