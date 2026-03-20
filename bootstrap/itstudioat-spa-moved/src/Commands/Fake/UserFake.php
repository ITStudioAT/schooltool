<?php

namespace Itstudioat\Spa\Commands\Fake;

use App\Models\User;
use Illuminate\Console\Command;

class UserFake extends Command
{
    protected $signature = 'user:fake {count=100}';

    protected $description = 'Create Fake Users using the UserFactory';

    public function handle()
    {
        $count = (int) $this->argument('count');

        $this->info("🚀 Creating {$count} fake users...");

        $users = User::factory()->count($count)->create();

        foreach ($users as $user) {
            $this->info("✅ Benutzer {$user->name} ({$user->email}) erstellt.");
        }

        $this->info("🎉 Done! {$count} users created.");
    }
}
