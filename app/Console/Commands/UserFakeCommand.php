<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:fake {count=100}')]
#[Description('Create fake users using the UserFactory')]
class UserFakeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->argument('count');

        $this->info("Creating {$count} fake users...");

        $users = User::factory()->count($count)->create();

        foreach ($users as $user) {
            $this->info("Benutzer {$user->name} ({$user->email}) erstellt.");
        }

        $this->info("Done! {$count} users created.");

        return self::SUCCESS;
    }
}
