<?php

namespace Itstudioat\Spa\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateUser extends Command
{
    // Kein Argument in der Signature definieren
    protected $signature = 'user:create';

    protected $description = 'Interaktiv einen neuen Benutzer erstellen';

    public function handle()
    {
        // Schrittweise Eingabe
        $first_name = $this->ask('Vorname');
        $last_name = $this->ask('Nachname');
        $email = $this->ask('E-Mail');
        $password = $this->secret('Passwort (wird ausgeblendet)');

        // Validierung
        if (User::where('email', $email)->exists()) {
            $this->error('Diese E-Mail-Adresse existiert bereits!');

            return;
        }

        // Benutzer erstellen
        $user = User::create([
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'password' => Hash::make($password),
            'confirmed_at' => now(),
        ]);

        $this->info("Benutzer {$user->first_name} {$user->last_name} erfolgreich erstellt.");
    }
}
