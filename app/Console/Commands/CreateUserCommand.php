<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('user:create')]
#[Description('Interaktiv einen neuen Benutzer erstellen')]
class CreateUserCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $firstName = $this->ask('Vorname');
        $lastName = $this->ask('Nachname');
        $email = $this->ask('E-Mail');
        $password = $this->secret('Passwort (wird ausgeblendet)');

        if (User::where('email', $email)->exists()) {
            $this->error('Diese E-Mail-Adresse existiert bereits!');

            return self::FAILURE;
        }

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make($password),
            'confirmed_at' => now(),
        ]);

        $this->info("Benutzer {$user->first_name} {$user->last_name} erfolgreich erstellt.");

        return self::SUCCESS;
    }
}
