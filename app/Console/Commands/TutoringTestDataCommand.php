<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\TutoringOffer;
use App\Models\TutoringOfferRequest;
use App\Models\TutoringSubject;
use App\Models\User;
use Database\Seeders\TutoringTestDataCleanupSeeder;
use Database\Seeders\TutoringTestDataSeeder;
use Database\Seeders\TutoringTestDataSmallSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TutoringTestDataCommand extends Command
{
    protected $signature = 'tutoring:test-data {action : add, add-small, remove, or create-test-user}';

    protected $description = 'Verwaltet Tutoring Test-Daten (add/add-small/remove/create-test-user)';

    private array $requestMessages = [
        'Hallo! Ich hätte Interesse an deinem Nachhilfe-Angebot. Könnten wir einen Termin ausmachen?',
        'Ich brauche dringend Hilfe in diesem Fach. Wann hättest du Zeit?',
        'Dein Angebot klingt sehr interessant. Wie läuft das genau ab?',
        'Ich würde gerne mehr über dein Nachhilfe-Angebot erfahren.',
        'Hast du noch freie Kapazitäten? Ich bräuchte Unterstützung.',
        'Ich interessiere mich für deine Nachhilfe. Was kostet eine Stunde?',
        'Könntest du mir bei der Vorbereitung auf die nächste Prüfung helfen?',
        'Ich habe Schwierigkeiten in diesem Fach und bräuchte Unterstützung.',
        'Wann und wo würde die Nachhilfe stattfinden?',
        'Ich möchte mich gerne für deine Nachhilfe anmelden.',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'add':
                $this->info('📝 Füge Tutoring Test-Daten hinzu (10 Schulen, ~10.000 Benutzer)...');
                $this->info('⏱️  Dies dauert ca. 30-60 Sekunden...');
                $this->call('db:seed', ['--class' => TutoringTestDataSeeder::class]);

                $this->info("\n👤 Erstelle Test-Benutzer mit Anfragen...");
                $this->createTestUser();
                break;

            case 'add-small':
                $this->info('📝 Füge kleine Test-Daten hinzu (1 Schule, 10 Schüler, 2 Angebote)...');
                $this->call('db:seed', ['--class' => TutoringTestDataSmallSeeder::class]);
                break;

            case 'remove':
                $this->info('🗑️  Entferne Tutoring Test-Daten...');
                $this->call('db:seed', ['--class' => TutoringTestDataCleanupSeeder::class]);
                break;

            case 'create-test-user':
                $this->info('👤 Erstelle Test-Benutzer mit Anfragen...');
                $this->createTestUser();
                break;

            default:
                $this->error('❌ Ungültige Aktion. Verwenden Sie "add", "add-small", "remove" oder "create-test-user".');
                $this->info('Verwendung:');
                $this->info('  php artisan tutoring:test-data add              - Fügt vollständige Test-Daten hinzu (10 Schulen)');
                $this->info('  php artisan tutoring:test-data add-small        - Fügt kleine Test-Daten hinzu (1 Schule)');
                $this->info('  php artisan tutoring:test-data remove           - Entfernt Test-Daten');
                $this->info('  php artisan tutoring:test-data create-test-user - Erstellt Test-Benutzer mit Anfragen');

                return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Erstellt einen Test-Benutzer mit OfferRequests
     */
    protected function createTestUser(): void
    {
        // Finde die Schulen anhand von short_name (verlässlicher)
        $agSalzburg = School::where('short_name', 'ABG-SB')->first();
        $agInnsbruck = School::where('short_name', 'ABG-IBK')->first();

        if (! $agSalzburg) {
            $this->error('❌ Akademisches Gymnasium Salzburg (ABG-SB) nicht gefunden!');
            $this->warn('⚠ Bitte stellen Sie sicher, dass "php artisan tutoring:test-data add" bereits ausgeführt wurde.');

            return;
        }

        if (! $agInnsbruck) {
            $this->error('❌ Akademisches Gymnasium Innsbruck (ABG-IBK) nicht gefunden!');
            $this->warn('⚠ Bitte stellen Sie sicher, dass "php artisan tutoring:test-data add" bereits ausgeführt wurde.');

            return;
        }

        $this->info("✓ Akademisches Gymnasium Salzburg gefunden (ID: {$agSalzburg->id})");
        $this->info("✓ Akademisches Gymnasium Innsbruck gefunden (ID: {$agInnsbruck->id})");

        // Prüfe ob Benutzer mit dieser E-Mail UND school_id bereits existiert
        $existingUser = User::where('email', 'hallo@itstudio.at')
            ->where('school_id', $agSalzburg->id)
            ->first();

        if ($existingUser) {
            // Benutzer existiert bereits für diese Schule - lösche nur die OfferRequests
            $this->warn("⚠ Benutzer hallo@itstudio.at existiert bereits für diese Schule (ID: {$existingUser->id}). Lösche nur OfferRequests...");

            // Lösche zugehörige OfferRequests
            $deletedRequests = TutoringOfferRequest::where('from_user_id', $existingUser->id)
                ->orWhere('to_user_id', $existingUser->id)
                ->count();
            TutoringOfferRequest::where('from_user_id', $existingUser->id)
                ->orWhere('to_user_id', $existingUser->id)
                ->delete();
            $this->info("✓ {$deletedRequests} alte OfferRequests gelöscht");

            $user = $existingUser;
            $this->info("✓ Verwende existierenden Benutzer (ID: {$user->id}, School ID: {$agSalzburg->id})");

        } else {
            // Kein existierender Benutzer für diese Schule - erstelle neuen
            $role = Role::firstOrCreate(['name' => 'tutoring_user']);

            $user = User::create([
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'hallo@itstudio.at',
                'password' => bcrypt('password'),
                'school_id' => $agSalzburg->id,
                'email_verified_at' => now(),
                'confirmed_at' => now(),
            ]);

            $user->assignRole($role);
            $this->info("✓ Neuer Benutzer erstellt (ID: {$user->id}, School ID: {$agSalzburg->id})");
        }

        // 1. Erstelle 40 OfferRequests VON diesem Benutzer
        $this->info("\n📤 Erstelle 40 OfferRequests VON diesem Benutzer...");
        $this->createOutgoingRequests($user, $agSalzburg, $agInnsbruck);

        // 2. Erstelle 40 OfferRequests AN diesen Benutzer
        $this->info("\n📥 Erstelle 40 OfferRequests AN diesen Benutzer...");
        $this->createIncomingRequests($user);

        $this->info("\n✅ Fertig! Test-Benutzer mit 80 OfferRequests erstellt.");
        $this->info('   E-Mail: hallo@itstudio.at');
        $this->info('   Passwort: password');
        $this->info("   User ID: {$user->id}");
    }

    /**
     * Erstellt 40 ausgehende OfferRequests (vom Test-Benutzer)
     */
    protected function createOutgoingRequests($user, $agSalzburg, $agInnsbruck): void
    {
        // Hole Angebote aus beiden Schulen
        $offersFromSalzburg = TutoringOffer::where('school_id', $agSalzburg->id)
            ->where('user_id', '!=', $user->id)
            ->inRandomOrder()
            ->limit(20)
            ->get();

        $offersFromInnsbruck = TutoringOffer::where('school_id', $agInnsbruck->id)
            ->inRandomOrder()
            ->limit(20)
            ->get();

        $allOffers = $offersFromSalzburg->concat($offersFromInnsbruck);

        if ($allOffers->count() < 40) {
            $this->warn("⚠ Nur {$allOffers->count()} Angebote gefunden (benötigt: 40)");
        }

        $created = 0;
        foreach ($allOffers as $offer) {
            if ($created >= 40) {
                break;
            }

            $isSent = rand(0, 100) > 30; // 70% wurden gesendet
            $isSeen = $isSent && rand(0, 100) > 40; // 60% der gesendeten wurden gesehen

            $sentAt = null;
            $lastSentAt = null;
            $seenAt = null;
            $lastSeenAt = null;
            $token = null;
            $tokenExpiresAt = null;

            if ($isSent) {
                $sentAt = now()->subDays(rand(1, 30));
                $lastSentAt = $sentAt;
                $token = Str::random(64);
                $tokenExpiresAt = now()->addDays(7);
            }

            if ($isSeen) {
                $seenAt = $sentAt->copy()->addHours(rand(1, 48));
                $lastSeenAt = $seenAt;
            }

            TutoringOfferRequest::create([
                'school_id' => $offer->school_id,
                'offer_id' => $offer->id,
                'from_user_id' => $user->id,
                'to_user_id' => $offer->user_id,
                'message' => $this->requestMessages[array_rand($this->requestMessages)],
                'is_serious' => rand(0, 100) > 20,
                'archived_at' => null,
                'token' => $token,
                'token_expires_at' => $tokenExpiresAt,
                'sent_at' => $sentAt,
                'last_sent_at' => $lastSentAt,
                'seen_at' => $seenAt,
                'last_seen_at' => $lastSeenAt,
                'created_at' => now()->subDays(rand(1, 60)),
            ]);

            $created++;
        }

        $this->info("✓ {$created} ausgehende OfferRequests erstellt");
    }

    /**
     * Erstellt 40 eingehende OfferRequests (an den Test-Benutzer)
     */
    protected function createIncomingRequests($user): void
    {
        // Hole zufällige Benutzer aus allen Schulen
        $fromUsers = User::where('id', '!=', $user->id)
            ->inRandomOrder()
            ->limit(40)
            ->get();

        if ($fromUsers->count() < 40) {
            $this->warn("⚠ Nur {$fromUsers->count()} Benutzer gefunden (benötigt: 40)");
        }

        // Hole Angebote des Test-Benutzers oder erstelle Dummy-Angebote
        $userOffers = TutoringOffer::where('user_id', $user->id)->get();

        if ($userOffers->isEmpty()) {
            $this->warn('⚠ Test-Benutzer hat keine Angebote. Erstelle Dummy-Angebote...');
            $userOffers = $this->createDummyOffers($user);
        }
        if ($userOffers->isEmpty()) {
            $this->warn('⚠ Keine Angebote verfuegbar. Ueberspringe eingehende OfferRequests.');

            return;
        }

        $created = 0;
        foreach ($fromUsers as $fromUser) {
            if ($created >= 40) {
                break;
            }

            // Wähle ein zufälliges Angebot des Test-Benutzers
            $offer = $userOffers->random();

            $isSent = rand(0, 100) > 30; // 70% wurden gesendet
            $isSeen = $isSent && rand(0, 100) > 40; // 60% der gesendeten wurden gesehen

            $sentAt = null;
            $lastSentAt = null;
            $seenAt = null;
            $lastSeenAt = null;
            $token = null;
            $tokenExpiresAt = null;

            if ($isSent) {
                $sentAt = now()->subDays(rand(1, 30));
                $lastSentAt = $sentAt;
                $token = Str::random(64);
                $tokenExpiresAt = now()->addDays(7);
            }

            if ($isSeen) {
                $seenAt = $sentAt->copy()->addHours(rand(1, 48));
                $lastSeenAt = $seenAt;
            }

            TutoringOfferRequest::create([
                'school_id' => $fromUser->school_id,
                'offer_id' => $offer->id,
                'from_user_id' => $fromUser->id,
                'to_user_id' => $user->id,
                'message' => $this->requestMessages[array_rand($this->requestMessages)],
                'is_serious' => rand(0, 100) > 20,
                'archived_at' => null,
                'token' => $token,
                'token_expires_at' => $tokenExpiresAt,
                'sent_at' => $sentAt,
                'last_sent_at' => $lastSentAt,
                'seen_at' => $seenAt,
                'last_seen_at' => $lastSeenAt,
                'created_at' => now()->subDays(rand(1, 60)),
            ]);

            $created++;
        }

        $this->info("✓ {$created} eingehende OfferRequests erstellt");
    }

    /**
     * Erstellt Dummy-Angebote für den Test-Benutzer
     */
    protected function createDummyOffers($user): Collection
    {
        $subjects = TutoringSubject::where('school_id', $user->school_id)
            ->inRandomOrder()
            ->limit(5)
            ->get();

        if ($subjects->isEmpty()) {
            $this->warn('⚠ Keine TutoringSubjects gefunden. Erstelle Dummy-Subjects...');
            $subjects = $this->createDummySubjects($user->school_id);
        }

        $offers = collect();

        foreach ($subjects as $subject) {
            $mustBeAccepted = rand(0, 1);
            $acceptedAt = $mustBeAccepted ? now()->subDays(rand(1, 30)) : null;
            $isActive = $acceptedAt !== null; // is_active nur true wenn accepted_at gesetzt ist

            $offer = TutoringOffer::create([
                'school_id' => $user->school_id,
                'user_id' => $user->id,
                'subject_id' => $subject->id,
                'title' => "Nachhilfe in {$subject->name}",
                'description' => 'Test-Angebot für Demo-Zwecke',
                'classes' => [
                    '1' => false,
                    '2' => false,
                    '3' => false,
                    '4' => false,
                    '5' => true,
                    '6' => true,
                    '7' => true,
                    '8' => true,
                    '9' => false,
                ],
                'time_table' => null,
                'active_until' => now()->addMonths(3)->format('Y-m-d'),
                'is_active' => $isActive,
                'price_per_hour' => rand(10, 25),
                'is_group' => rand(0, 1),
                'max_group_members' => rand(0, 1) ? rand(2, 5) : null,
                'must_be_accepted' => $mustBeAccepted,
                'accepted_at' => $acceptedAt,
                'created_at' => now()->subDays(rand(1, 60)),
            ]);

            $offers->push($offer);
        }

        $this->info("✓ {$offers->count()} Dummy-Angebote erstellt");

        return $offers;
    }

    /**
     * Erstellt Dummy-Subjects fuer eine Schule
     */
    protected function createDummySubjects(int $schoolId): Collection
    {
        $names = [
            ['MAT', 'Mathematik'],
            ['DEU', 'Deutsch'],
            ['ENG', 'Englisch'],
            ['BIO', 'Biologie'],
            ['GEO', 'Geografie'],
        ];

        $subjects = collect();
        foreach ($names as [$short, $long]) {
            $subjects->push(TutoringSubject::create([
                'school_id' => $schoolId,
                'short_name' => $short,
                'long_name' => $long,
                'must_be_accepted' => true,
                'email_mentors' => null,
            ]));
        }

        $this->info("✓ {$subjects->count()} Dummy-Subjects erstellt");

        return $subjects;
    }
}
