<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\TutoringOffer;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Kleiner Test-Seeder für schnelle Tests (nur 1 Schule, 10 Schüler, 2 Angebote)
 */
class TutoringTestDataSmallSeeder extends Seeder
{
    private array $firstNames = [
        'männlich' => ['Alexander', 'Andreas', 'Benjamin', 'Christian', 'Daniel'],
        'weiblich' => ['Alexandra', 'Anna', 'Caroline', 'Christina', 'Clara'],
    ];

    private array $lastNames = ['Bauer', 'Berger', 'Brunner', 'Eder', 'Egger'];

    private array $subjects = [
        ['short' => 'M', 'long' => 'Mathematik'],
        ['short' => 'D', 'long' => 'Deutsch'],
        ['short' => 'E', 'long' => 'Englisch'],
    ];

    private array $classes = ['1. Klasse', '2. Klasse', '3. Klasse', '4. Klasse'];

    private array $weekdays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag'];

    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->command->info('🎓 Starte Kleiner Tutoring Test (1 Schule)...');

            // Erstelle Rollen
            foreach (['tutoring_user', 'teacher', 'super_admin'] as $roleName) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            }

            // Erstelle 1 Test-Schule
            $school = School::create([
                'short_name' => 'TEST-SCHOOL',
                'long_name' => 'Test Gymnasium',
                'email' => 'office@test-school.at',
                'logo' => null,
                'is_selectable' => true,
            ]);

            $this->command->info('✓ Schule erstellt: '.$school->long_name);

            // Füge Nachhilfetool Lizenz hinzu (ID: 2) mit Gültigkeit bis 2026-07-10
            $school->licences()->attach(2, ['valid_until' => '2026-07-10']);
            $this->command->info('✓ Nachhilfetool Lizenz hinzugefügt (gültig bis 2026-07-10)');

            // Erstelle SchoolTool Record
            SchoolTool::create([
                'school_id' => $school->id,
                'tutoring_student_must_be_confirmed' => 1,
                'tutoring_confirmer_email' => 'kron@naturwelt.at',
                'tutoring_max_offers_per_student' => 4, // Mitte zwischen 3-5
            ]);
            $this->command->info('✓ SchoolTool Einstellungen erstellt');

            // Erstelle Super-Admin (kron@naturwelt.at)
            $superAdmin = User::create([
                'school_id' => $school->id,
                'email' => 'kron@naturwelt.at',
                'password' => Hash::make('password'),
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'is_active' => 1,
                'confirmed_at' => now(),
                'email_verified_at' => now(),
            ]);
            $superAdmin->assignRole('super_admin');
            $this->command->info('✓ Super-Admin erstellt: kron@naturwelt.at');

            // Erstelle 2 Lehrer (ZUERST, da Fächer auf Lehrer-Emails referenzieren)
            $teachers = [];
            for ($i = 0; $i < 2; $i++) {
                // Bestimme Geschlecht (sex): mostly m oder f, rarely d
                $sexRand = rand(1, 100);
                if ($sexRand <= 50) {
                    $sex = 'm'; // 50% männlich
                } elseif ($sexRand <= 98) {
                    $sex = 'f'; // 48% weiblich
                } else {
                    $sex = 'd'; // 2% divers
                }

                $teacher = User::create([
                    'school_id' => $school->id,
                    'email' => "teacher{$i}@test-school.at",
                    'password' => Hash::make('password'),
                    'first_name' => 'Lehrer',
                    'last_name' => "Test{$i}",
                    'short' => 'TEST',
                    'sex' => $sex,
                    'is_active' => 1,
                    'confirmed_at' => now(),
                    'email_verified_at' => now(),
                ]);
                $teacher->assignRole('teacher');
                $teachers[] = $teacher;
            }

            $this->command->info('✓ 2 Lehrer erstellt');

            // Erstelle 3 Fächer (mit echten Lehrer-Emails)
            $subjectModels = [];
            foreach ($this->subjects as $subject) {
                // Wähle zufällig 1-2 Lehrer aus den erstellten Lehrern
                $mentorCount = min(rand(1, 2), count($teachers));
                $shuffledTeachers = $teachers;
                shuffle($shuffledTeachers);
                $selectedTeachers = array_slice($shuffledTeachers, 0, $mentorCount);
                $mentors = array_map(fn ($teacher) => $teacher->email, $selectedTeachers);

                $subjectModels[] = TutoringSubject::create([
                    'school_id' => $school->id,
                    'short_name' => $subject['short'],
                    'long_name' => $subject['long'],
                    'must_be_accepted' => true,
                    'email_mentors' => $mentors,
                ]);
            }

            $this->command->info('✓ '.count($subjectModels).' Fächer erstellt');

            // Erstelle 10 Schüler
            $students = [];
            for ($i = 0; $i < 10; $i++) {
                $gender = rand(0, 1) ? 'männlich' : 'weiblich';
                $firstName = $this->firstNames[$gender][array_rand($this->firstNames[$gender])];
                $lastName = $this->lastNames[array_rand($this->lastNames)];

                // Bestimme Geschlecht (sex): mostly m oder f, rarely d
                $sexRand = rand(1, 100);
                if ($sexRand <= 50) {
                    $sex = 'm'; // 50% männlich
                } elseif ($sexRand <= 98) {
                    $sex = 'f'; // 48% weiblich
                } else {
                    $sex = 'd'; // 2% divers
                }

                $student = User::create([
                    'school_id' => $school->id,
                    'email' => strtolower($firstName.'.'.$lastName.$i).'@test-school.at',
                    'password' => Hash::make('password'),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'sex' => $sex,
                    'is_active' => 1,
                    'confirmed_at' => now(),
                    'email_verified_at' => now(),
                ]);
                $student->assignRole('tutoring_user');
                $students[] = $student;
            }

            $this->command->info('✓ 10 Schüler erstellt');

            // Erstelle 2 Tutoring-Angebote
            for ($i = 0; $i < 2; $i++) {
                $student = $students[$i];
                $subject = $subjectModels[array_rand($subjectModels)];

                // Erstelle Zeitplan
                $timeTable = [
                    [
                        'day' => 'Montag',
                        'from' => '15:00',
                        'to' => '17:00',
                    ],
                ];

                // Erstelle Klassen-Struktur (1-9 mit boolean)
                $classes = [
                    '1' => true,
                    '2' => true,
                    '3' => true,
                    '4' => true,
                    '5' => false,
                    '6' => false,
                    '7' => false,
                    '8' => false,
                    '9' => false,
                ];

                // Wenn must_be_accepted == false, dann email_mentor = null und accepted_at = now()
                // Sonst wähle einen zufälligen Mentor aus den Subject-Mentoren
                $mentorEmail = null;
                if ($subject->must_be_accepted && $subject->email_mentors && count($subject->email_mentors) > 0) {
                    $mentorEmail = $subject->email_mentors[array_rand($subject->email_mentors)];
                }
                $acceptedAt = now()->format('Y-m-d');

                TutoringOffer::create([
                    'school_id' => $school->id,
                    'user_id' => $student->id,
                    'subject_id' => $subject->id,
                    'title' => $subject->long_name.' Nachhilfe',
                    'description' => 'Test-Angebot für '.$subject->long_name,
                    'classes' => $classes,
                    'time_table' => $timeTable,
                    'active_until' => now()->addMonths(3)->format('Y-m-d'),
                    'is_active' => true,
                    'price_per_hour' => 15,
                    'is_group' => false,
                    'max_group_members' => null,
                    'must_be_accepted' => $subject->must_be_accepted,
                    'email_mentor' => $mentorEmail,
                    'accepted_at' => $acceptedAt,
                    'click_count' => 0,
                    'visible_for_other_schools' => rand(0, 1) === 1, // 50% sichtbar für andere Schulen
                ]);
            }

            $this->command->info('✓ 2 Angebote erstellt');

            DB::commit();
            $this->command->info('✅ Kleiner Test erfolgreich!');
            $this->command->info('📧 Login: Beliebige Email (z.B. alexander.bauer0@test-school.at)');
            $this->command->info('🔑 Passwort: password');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Fehler: '.$e->getMessage());
            $this->command->error($e->getTraceAsString());
            throw $e;
        }
    }
}
