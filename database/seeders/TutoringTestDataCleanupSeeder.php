<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\TutoringOffer;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TutoringTestDataCleanupSeeder extends Seeder
{
    // Liste der Test-Schulen (muss mit TutoringTestDataSeeder übereinstimmen)
    private array $testSchoolShortNames = [
        'TEST-SCHOOL', // Kleine Test-Schule
        'AKG', 'BRG1', 'GRG3', 'BRG14', 'GRG21', 'BRG4', 'GRG19', 'BRG18', 'GRG23', 'BORG3',
        'STG', 'BRG-KR', 'GYM-STP', 'BRG-WN', 'GYM-MD', 'BG-BN', 'BRG-TU', 'GRG-AM', 'BG-KL', 'BRG-KO',
        'BG-GR', 'LG-GR', 'BRG-LB', 'GYM-FE', 'BRG-KF', 'GYM-WE', 'BG-BR', 'GYM-VO', 'BRG-KAP', 'GRG-JU',
        'BG-LZ', 'PG-LZ', 'BRG-WE-LZ', 'GYM-ST', 'BRG-TR', 'GRG-EF', 'BG-RI', 'GYM-BR', 'BRG-GM', 'GRG-VK',
        'BG-SB', 'ABG-SB', 'BRG-HA', 'GYM-ZS', 'BG-TB', 'GRG-ST-JO', 'BRG-BL', 'GYM-SK', 'BG-ST', 'GRG-OB',
        'BG-IBK', 'ABG-IBK', 'BRG-KU', 'GYM-IM', 'BG-LI', 'GRG-LAN', 'BRG-SC', 'GYM-RE', 'BG-KI', 'GRG-WÖ',
        'BG-BZ', 'GYM-DO', 'BRG-FE', 'GRG-BL', 'BG-EG', 'GYM-LU', 'BRG-HO', 'GRG-GO', 'BG-RK', 'GYM-NE',
        'BG-VL', 'PG-VL', 'BRG-KL', 'GYM-SP', 'BG-WO', 'GRG-FE-KT', 'BRG-ST-VE', 'GYM-HE', 'BG-VÖ', 'GRG-FR',
        'BG-EI', 'GYM-MA', 'BRG-NE-BG', 'GRG-OB-BG', 'BG-OW', 'GYM-GÜ', 'BRG-JE', 'GRG-PI', 'BG-RU', 'GYM-FR-BG',
        'BRG-HO-LZ', 'GYM-AN', 'BG-PG', 'GRG-GR-LZ', 'BRG-RO', 'GYM-SC-LZ', 'BG-FR-LZ', 'GRG-KI-LZ',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🧹 Starte Bereinigung der Tutoring Test Daten...');

        if (!$this->command->confirm('⚠️  Sind Sie sicher, dass Sie ALLE Test-Daten löschen möchten? Dies kann nicht rückgängig gemacht werden!', false)) {
            $this->command->info('Bereinigung abgebrochen.');
            return;
        }

        DB::beginTransaction();

        try {
            // Finde alle Test-Schulen
            $testSchools = School::whereIn('short_name', $this->testSchoolShortNames)->get();
            $schoolIds = $testSchools->pluck('id')->toArray();

            if (empty($schoolIds)) {
                $this->command->info('Keine Test-Schulen gefunden. Möglicherweise wurden die Daten bereits gelöscht.');
                DB::commit();
                return;
            }

            $this->command->info('Gefundene Test-Schulen: ' . count($testSchools));

            // Lösche Tutoring Angebote
            $offersCount = TutoringOffer::whereIn('school_id', $schoolIds)->count();
            TutoringOffer::whereIn('school_id', $schoolIds)->delete();
            $this->command->info("✓ {$offersCount} Tutoring Angebote gelöscht");

            // Lösche Tutoring Fächer
            $subjectsCount = TutoringSubject::whereIn('school_id', $schoolIds)->count();
            TutoringSubject::whereIn('school_id', $schoolIds)->delete();
            $this->command->info("✓ {$subjectsCount} Tutoring Fächer gelöscht");

            // Lösche Benutzer (Schüler und Lehrer)
            $usersCount = User::whereIn('school_id', $schoolIds)->count();

            // Lösche queue_tests Einträge (Foreign Key Constraint)
            $queueTestsCount = DB::table('queue_tests')
                ->whereIn('user_id', function ($query) use ($schoolIds) {
                    $query->select('id')
                        ->from('users')
                        ->whereIn('school_id', $schoolIds);
                })
                ->count();

            DB::table('queue_tests')
                ->whereIn('user_id', function ($query) use ($schoolIds) {
                    $query->select('id')
                        ->from('users')
                        ->whereIn('school_id', $schoolIds);
                })
                ->delete();

            if ($queueTestsCount > 0) {
                $this->command->info("✓ {$queueTestsCount} Queue-Tests gelöscht");
            }

            // Lösche Rollen-Zuweisungen
            DB::table('model_has_roles')
                ->whereIn('model_id', function ($query) use ($schoolIds) {
                    $query->select('id')
                        ->from('users')
                        ->whereIn('school_id', $schoolIds);
                })
                ->where('model_type', User::class)
                ->delete();

            // Lösche die Benutzer
            User::whereIn('school_id', $schoolIds)->delete();
            $this->command->info("✓ {$usersCount} Benutzer (Schüler & Lehrer) gelöscht");

            // Lösche Schul-Lizenzen
            $licencesCount = DB::table('school_licences')
                ->whereIn('school_id', $schoolIds)
                ->count();

            DB::table('school_licences')
                ->whereIn('school_id', $schoolIds)
                ->delete();

            if ($licencesCount > 0) {
                $this->command->info("✓ {$licencesCount} Schul-Lizenzen gelöscht");
            }

            // Lösche Schulen
            $schoolsCount = count($testSchools);
            School::whereIn('id', $schoolIds)->delete();
            $this->command->info("✓ {$schoolsCount} Schulen gelöscht");

            DB::commit();

            $this->command->info('✅ Tutoring Test Daten erfolgreich bereinigt!');
            $this->command->info('📊 Zusammenfassung:');
            $this->command->info("   - Schulen gelöscht: {$schoolsCount}");
            $this->command->info("   - Benutzer gelöscht: {$usersCount}");
            $this->command->info("   - Fächer gelöscht: {$subjectsCount}");
            $this->command->info("   - Tutoring Angebote gelöscht: {$offersCount}");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Fehler beim Bereinigen: ' . $e->getMessage());
            throw $e;
        }
    }
}
