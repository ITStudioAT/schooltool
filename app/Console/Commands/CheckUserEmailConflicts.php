<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckUserEmailConflicts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:check-conflicts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for email+school_id conflicts before migration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for potential email conflicts...');

        // Find duplicate email+school_id combinations
        $conflicts = DB::table('users')
            ->select('email', 'school_id', DB::raw('COUNT(*) as count'))
            ->groupBy('email', 'school_id')
            ->having('count', '>', 1)
            ->get();

        if ($conflicts->isEmpty()) {
            $this->info('✓ No conflicts found! Safe to migrate.');
            return Command::SUCCESS;
        }

        $this->error('⚠ Found ' . $conflicts->count() . ' conflict(s):');

        foreach ($conflicts as $conflict) {
            $this->warn("  Email: {$conflict->email}, School ID: {$conflict->school_id}, Count: {$conflict->count}");

            // Show the conflicting user IDs
            $users = User::where('email', $conflict->email)
                ->where('school_id', $conflict->school_id)
                ->get(['id', 'email', 'first_name', 'last_name']);

            foreach ($users as $user) {
                $this->line("    → User ID {$user->id}: {$user->first_name} {$user->last_name}");
            }
        }

        $this->newLine();
        $this->error('Please resolve these conflicts before running the migration!');

        return Command::FAILURE;
    }
}
