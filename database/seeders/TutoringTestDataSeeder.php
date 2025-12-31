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

class TutoringTestDataSeeder extends Seeder
{
    // Echte österreichische Gymnasien
    private array $austrianSchools = [
        ['short_name' => 'AKG', 'long_name' => 'Akademisches Gymnasium Wien', 'domain' => 'akg-wien'],
        ['short_name' => 'BRG1', 'long_name' => 'BRG 1 Stubenbastei Wien', 'domain' => 'brg1'],
        ['short_name' => 'GRG3', 'long_name' => 'GRG 3 Hagenmüllergasse Wien', 'domain' => 'grg3'],
        ['short_name' => 'BRG14', 'long_name' => 'BRG 14 Linzer Straße Wien', 'domain' => 'brg14'],
        ['short_name' => 'GRG21', 'long_name' => 'GRG 21 Ödenburger Straße Wien', 'domain' => 'grg21'],
        ['short_name' => 'BRG4', 'long_name' => 'BRG 4 Waltergasse Wien', 'domain' => 'brg4'],
        ['short_name' => 'GRG19', 'long_name' => 'GRG 19 Billrothstraße Wien', 'domain' => 'grg19'],
        ['short_name' => 'BRG18', 'long_name' => 'BRG 18 Schopenhauerstraße Wien', 'domain' => 'brg18'],
        ['short_name' => 'GRG23', 'long_name' => 'GRG 23 Draschestraße Wien', 'domain' => 'grg23'],
        ['short_name' => 'BORG3', 'long_name' => 'BORG 3 Landstraßer Hauptstraße Wien', 'domain' => 'borg3'],
        ['short_name' => 'STG', 'long_name' => 'Stiftsgymnasium Melk', 'domain' => 'stiftmelk'],
        ['short_name' => 'BRG-KR', 'long_name' => 'BRG Krems', 'domain' => 'brgkrems'],
        ['short_name' => 'GYM-STP', 'long_name' => 'Gymnasium St. Pölten', 'domain' => 'gymstpoelten'],
        ['short_name' => 'BRG-WN', 'long_name' => 'BRG Wiener Neustadt', 'domain' => 'brgwn'],
        ['short_name' => 'GYM-MD', 'long_name' => 'Gymnasium Mödling', 'domain' => 'gymmoedling'],
        ['short_name' => 'BG-BN', 'long_name' => 'Bundesgymnasium Baden', 'domain' => 'bgbaden'],
        ['short_name' => 'BRG-TU', 'long_name' => 'BRG Tulln', 'domain' => 'brgtulln'],
        ['short_name' => 'GRG-AM', 'long_name' => 'GRG Amstetten', 'domain' => 'grgamstetten'],
        ['short_name' => 'BG-KL', 'long_name' => 'Bundesgymnasium Klosterneuburg', 'domain' => 'bgklosterneuburg'],
        ['short_name' => 'BRG-KO', 'long_name' => 'BRG Korneuburg', 'domain' => 'brgkorneuburg'],
        ['short_name' => 'BG-GR', 'long_name' => 'Bundesgymnasium Graz', 'domain' => 'bggraz'],
        ['short_name' => 'LG-GR', 'long_name' => 'Lichtenfelsgasse Gymnasium Graz', 'domain' => 'lichtenfels-graz'],
        ['short_name' => 'BRG-LB', 'long_name' => 'BRG Leibnitz', 'domain' => 'brgleibnitz'],
        ['short_name' => 'GYM-FE', 'long_name' => 'Gymnasium Feldbach', 'domain' => 'gymfeldbach'],
        ['short_name' => 'BRG-KF', 'long_name' => 'BRG Köflach', 'domain' => 'brgkoeflach'],
        ['short_name' => 'GYM-WE', 'long_name' => 'Gymnasium Weiz', 'domain' => 'gymweiz'],
        ['short_name' => 'BG-BR', 'long_name' => 'Bundesgymnasium Bruck/Mur', 'domain' => 'bgbruck'],
        ['short_name' => 'GYM-VO', 'long_name' => 'Gymnasium Voitsberg', 'domain' => 'gymvoitsberg'],
        ['short_name' => 'BRG-KAP', 'long_name' => 'BRG Kapfenberg', 'domain' => 'brgkapfenberg'],
        ['short_name' => 'GRG-JU', 'long_name' => 'GRG Judenburg', 'domain' => 'grgjudenburg'],
        ['short_name' => 'BG-LZ', 'long_name' => 'Bundesgymnasium Linz', 'domain' => 'bglinz'],
        ['short_name' => 'PG-LZ', 'long_name' => 'Petrinum Gymnasium Linz', 'domain' => 'petrinum'],
        ['short_name' => 'BRG-WE-LZ', 'long_name' => 'BRG Wels', 'domain' => 'brgwels'],
        ['short_name' => 'GYM-ST', 'long_name' => 'Gymnasium Steyr', 'domain' => 'gymsteyr'],
        ['short_name' => 'BRG-TR', 'long_name' => 'BRG Traun', 'domain' => 'brgtraun'],
        ['short_name' => 'GRG-EF', 'long_name' => 'GRG Eferding', 'domain' => 'grgeferding'],
        ['short_name' => 'BG-RI', 'long_name' => 'Bundesgymnasium Ried', 'domain' => 'bgried'],
        ['short_name' => 'GYM-BR', 'long_name' => 'Gymnasium Braunau', 'domain' => 'gymbraunau'],
        ['short_name' => 'BRG-GM', 'long_name' => 'BRG Gmunden', 'domain' => 'brggmunden'],
        ['short_name' => 'GRG-VK', 'long_name' => 'GRG Vöcklabruck', 'domain' => 'grgvoecklabruck'],
        ['short_name' => 'BG-SB', 'long_name' => 'Bundesgymnasium Salzburg', 'domain' => 'bgsalzburg'],
        ['short_name' => 'ABG-SB', 'long_name' => 'Akademisches Gymnasium Salzburg', 'domain' => 'akg-salzburg'],
        ['short_name' => 'BRG-HA', 'long_name' => 'BRG Hallein', 'domain' => 'brghallein'],
        ['short_name' => 'GYM-ZS', 'long_name' => 'Gymnasium Zell am See', 'domain' => 'gymzellamsee'],
        ['short_name' => 'BG-TB', 'long_name' => 'Bundesgymnasium Tamsweg', 'domain' => 'bgtamsweg'],
        ['short_name' => 'GRG-ST-JO', 'long_name' => 'GRG St. Johann/Pongau', 'domain' => 'grgstjohann'],
        ['short_name' => 'BRG-BL', 'long_name' => 'BRG Bischofshofen', 'domain' => 'brgbischofshofen'],
        ['short_name' => 'GYM-SK', 'long_name' => 'Gymnasium Seekirchen', 'domain' => 'gymseek irchen'],
        ['short_name' => 'BG-ST', 'long_name' => 'Bundesgymnasium Straßwalchen', 'domain' => 'bgstrasswalchen'],
        ['short_name' => 'GRG-OB', 'long_name' => 'GRG Oberndorf', 'domain' => 'grgobern dorf'],
        ['short_name' => 'BG-IBK', 'long_name' => 'Bundesgymnasium Innsbruck', 'domain' => 'bginnsbruck'],
        ['short_name' => 'ABG-IBK', 'long_name' => 'Akademisches Gymnasium Innsbruck', 'domain' => 'akg-innsbruck'],
        ['short_name' => 'BRG-KU', 'long_name' => 'BRG Kufstein', 'domain' => 'brgkufstein'],
        ['short_name' => 'GYM-IM', 'long_name' => 'Gymnasium Imst', 'domain' => 'gymimst'],
        ['short_name' => 'BG-LI', 'long_name' => 'Bundesgymnasium Lienz', 'domain' => 'bglienz'],
        ['short_name' => 'GRG-LAN', 'long_name' => 'GRG Landeck', 'domain' => 'grglandeck'],
        ['short_name' => 'BRG-SC', 'long_name' => 'BRG Schwaz', 'domain' => 'brgschwaz'],
        ['short_name' => 'GYM-RE', 'long_name' => 'Gymnasium Reutte', 'domain' => 'gymreutte'],
        ['short_name' => 'BG-KI', 'long_name' => 'Bundesgymnasium Kitzbühel', 'domain' => 'bgkitzbue hel'],
        ['short_name' => 'GRG-WÖ', 'long_name' => 'GRG Wörgl', 'domain' => 'grgwoergl'],
        ['short_name' => 'BG-BZ', 'long_name' => 'Bundesgymnasium Bregenz', 'domain' => 'bgbregenz'],
        ['short_name' => 'GYM-DO', 'long_name' => 'Gymnasium Dornbirn', 'domain' => 'gymdornbirn'],
        ['short_name' => 'BRG-FE', 'long_name' => 'BRG Feldkirch', 'domain' => 'brgfeldkirch'],
        ['short_name' => 'GRG-BL', 'long_name' => 'GRG Bludenz', 'domain' => 'grgbludenz'],
        ['short_name' => 'BG-EG', 'long_name' => 'Bundesgymnasium Egg', 'domain' => 'bgegg'],
        ['short_name' => 'GYM-LU', 'long_name' => 'Gymnasium Lustenau', 'domain' => 'gymlustenau'],
        ['short_name' => 'BRG-HO', 'long_name' => 'BRG Hohenems', 'domain' => 'brghohenems'],
        ['short_name' => 'GRG-GO', 'long_name' => 'GRG Götzis', 'domain' => 'grggoetzis'],
        ['short_name' => 'BG-RK', 'long_name' => 'Bundesgymnasium Rankweil', 'domain' => 'bgrankweil'],
        ['short_name' => 'GYM-NE', 'long_name' => 'Gymnasium Nenzing', 'domain' => 'gymnenzing'],
        ['short_name' => 'BG-VL', 'long_name' => 'Bundesgymnasium Villach', 'domain' => 'bgvillach'],
        ['short_name' => 'PG-VL', 'long_name' => 'Peraugymnasium Villach', 'domain' => 'perau-villach'],
        ['short_name' => 'BRG-KL', 'long_name' => 'BRG Klagenfurt', 'domain' => 'brgklagenfurt'],
        ['short_name' => 'GYM-SP', 'long_name' => 'Gymnasium Spittal/Drau', 'domain' => 'gymspittal'],
        ['short_name' => 'BG-WO', 'long_name' => 'Bundesgymnasium Wolfsberg', 'domain' => 'bgwolfsberg'],
        ['short_name' => 'GRG-FE-KT', 'long_name' => 'GRG Feldkirchen', 'domain' => 'grgfeldkirchen'],
        ['short_name' => 'BRG-ST-VE', 'long_name' => 'BRG St. Veit/Glan', 'domain' => 'brgstveit'],
        ['short_name' => 'GYM-HE', 'long_name' => 'Gymnasium Hermagor', 'domain' => 'gymhermagor'],
        ['short_name' => 'BG-VÖ', 'long_name' => 'Bundesgymnasium Völkermarkt', 'domain' => 'bgvoelkermarkt'],
        ['short_name' => 'GRG-FR', 'long_name' => 'GRG Friesach', 'domain' => 'grgfriesach'],
        ['short_name' => 'BG-EI', 'long_name' => 'Bundesgymnasium Eisenstadt', 'domain' => 'bgeisenstadt'],
        ['short_name' => 'GYM-MA', 'long_name' => 'Gymnasium Mattersburg', 'domain' => 'gymmattersburg'],
        ['short_name' => 'BRG-NE-BG', 'long_name' => 'BRG Neusiedl am See', 'domain' => 'brgneusiedl'],
        ['short_name' => 'GRG-OB-BG', 'long_name' => 'GRG Oberpullendorf', 'domain' => 'grgoberpullendorf'],
        ['short_name' => 'BG-OW', 'long_name' => 'Bundesgymnasium Oberwart', 'domain' => 'bgoberwart'],
        ['short_name' => 'GYM-GÜ', 'long_name' => 'Gymnasium Güssing', 'domain' => 'gymguessing'],
        ['short_name' => 'BRG-JE', 'long_name' => 'BRG Jennersdorf', 'domain' => 'brgjennersdorf'],
        ['short_name' => 'GRG-PI', 'long_name' => 'GRG Pinkafeld', 'domain' => 'grgpinkafeld'],
        ['short_name' => 'BG-RU', 'long_name' => 'Bundesgymnasium Rust', 'domain' => 'bgrust'],
        ['short_name' => 'GYM-FR-BG', 'long_name' => 'Gymnasium Frauenkirchen', 'domain' => 'gymfrauenkirchen'],
        ['short_name' => 'BRG-HO-LZ', 'long_name' => 'BRG Hörsching', 'domain' => 'brghoersching'],
        ['short_name' => 'GYM-AN', 'long_name' => 'Gymnasium Ansfelden', 'domain' => 'gymansfelden'],
        ['short_name' => 'BG-PG', 'long_name' => 'Bundesgymnasium Perg', 'domain' => 'bgperg'],
        ['short_name' => 'GRG-GR-LZ', 'long_name' => 'GRG Grieskirchen', 'domain' => 'grggrieskirchen'],
        ['short_name' => 'BRG-RO', 'long_name' => 'BRG Rohrbach', 'domain' => 'brgrohrbach'],
        ['short_name' => 'GYM-SC-LZ', 'long_name' => 'Gymnasium Schärding', 'domain' => 'gymschaerding'],
        ['short_name' => 'BG-FR-LZ', 'long_name' => 'Bundesgymnasium Freistadt', 'domain' => 'bgfreistadt'],
        ['short_name' => 'GRG-KI-LZ', 'long_name' => 'GRG Kirchdorf', 'domain' => 'grgkirchdorf'],
    ];

    // Häufige österreichische Vornamen
    private array $firstNames = [
        'männlich' => [
            'Alexander', 'Andreas', 'Benjamin', 'Christian', 'Daniel', 'David', 'Dominik',
            'Fabian', 'Felix', 'Florian', 'Franz', 'Georg', 'Jakob', 'Johannes', 'Jonas',
            'Julian', 'Lukas', 'Markus', 'Martin', 'Matthias', 'Maximilian', 'Michael',
            'Niklas', 'Noah', 'Paul', 'Philipp', 'Rafael', 'Samuel', 'Sebastian', 'Simon',
            'Stefan', 'Thomas', 'Tobias', 'Valentin', 'Vincent', 'Wolfgang',
        ],
        'weiblich' => [
            'Alexandra', 'Anna', 'Caroline', 'Christina', 'Clara', 'Elena', 'Elisabeth',
            'Emma', 'Eva', 'Hannah', 'Isabel', 'Johanna', 'Julia', 'Katharina', 'Laura',
            'Lea', 'Lena', 'Lisa', 'Magdalena', 'Maria', 'Marie', 'Marlene', 'Mia',
            'Nina', 'Paula', 'Sarah', 'Sophie', 'Sophia', 'Teresa', 'Valentina', 'Vanessa',
        ],
    ];

    // Häufige österreichische Nachnamen
    private array $lastNames = [
        'Bauer', 'Berger', 'Brunner', 'Eder', 'Egger', 'Fischer', 'Gruber', 'Haas',
        'Hofer', 'Huber', 'Koller', 'Lang', 'Leitner', 'Maier', 'Mayer', 'Moser',
        'Müller', 'Neumayer', 'Pichler', 'Reiter', 'Schmid', 'Schmidt', 'Schneider',
        'Steiner', 'Wagner', 'Weber', 'Weiss', 'Winkler', 'Wolf', 'Zeller',
        'Aigner', 'Ebner', 'Fuchs', 'Gartner', 'Hinterberger', 'Holzer', 'Horvath',
        'Jäger', 'Kaiser', 'Kirchner', 'Lechner', 'Mayr', 'Novak', 'Obermayer',
        'Plank', 'Riedl', 'Schuster', 'Schwarz', 'Stadler', 'Wimmer',
    ];

    // Österreichische Schulfächer
    private array $subjects = [
        ['short' => 'M', 'long' => 'Mathematik'],
        ['short' => 'D', 'long' => 'Deutsch'],
        ['short' => 'E', 'long' => 'Englisch'],
        ['short' => 'F', 'long' => 'Französisch'],
        ['short' => 'L', 'long' => 'Latein'],
        ['short' => 'PH', 'long' => 'Physik'],
        ['short' => 'CH', 'long' => 'Chemie'],
        ['short' => 'BIO', 'long' => 'Biologie'],
        ['short' => 'GWK', 'long' => 'Geografie und Wirtschaftskunde'],
        ['short' => 'GSPB', 'long' => 'Geschichte und Politische Bildung'],
    ];

    // Mögliche Klassen/Schulstufen
    private array $classes = [
        '1. Klasse', '2. Klasse', '3. Klasse', '4. Klasse',
        '5. Klasse', '6. Klasse', '7. Klasse', '8. Klasse',
    ];

    // Wochentage für Zeitplan
    private array $weekdays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag'];

    // Cached hashed password (performance optimization)
    private string $hashedPassword;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            $this->command->info('🎓 Starte Tutoring Test Daten Seeding...');

            // Hash password ONCE for all users (major performance boost!)
            $this->command->info('🔐 Generiere Passwort-Hash...');
            $this->hashedPassword = Hash::make('password');
            $this->command->info('✓ Passwort-Hash erstellt');

            // Erstelle Rollen falls nicht vorhanden (ohne Transaktion)
            $this->createRoles();

            // Erstelle 100 Schulen (ohne Transaktion - sofort committen)
            $schools = $this->createSchools();
            $this->command->info('✓ ' . count($schools) . ' Schulen erstellt');

            // Für jede Schule - JEDE SCHULE in eigener Transaktion
            $schoolCount = count($schools);
            foreach ($schools as $index => $school) {
                DB::beginTransaction();

                try {
                    $currentIndex = $index + 1;
                    $this->command->info("Verarbeite Schule {$currentIndex}/{$schoolCount}: {$school->long_name}");

                    // Erstelle 10 Fächer pro Schule
                    $subjects = $this->createSubjects($school);

                    // Erstelle 10 Lehrer pro Schule
                    $teachers = $this->createTeachers($school, 10);

                    // Erstelle 1000 Schüler pro Schule
                    $students = $this->createStudents($school, 1000);

                    // Erstelle 200 Tutoring Angebote
                    $this->createTutoringOffers($school, $students, $subjects, 200);

                    DB::commit();
                    $this->command->info("  ✓ Schule {$currentIndex}/{$schoolCount} erfolgreich gespeichert");
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->command->error("  ❌ Fehler bei Schule {$currentIndex}: {$e->getMessage()}");
                    $this->command->warn("  ⚠️  Schule wird übersprungen, fahre mit nächster fort...");
                    continue; // Fahre mit nächster Schule fort
                }
            }

            $this->command->info('✅ Tutoring Test Daten erfolgreich erstellt!');
            $this->command->info('📊 Zusammenfassung:');
            $this->command->info('   - Schulen: ' . School::whereIn('short_name', array_column($this->austrianSchools, 'short_name'))->count());
            $this->command->info('   - Super-Admins: ' . User::role('super_admin')->where('email', 'kron@naturwelt.at')->count());
            $this->command->info('   - Schüler: ~' . User::role('tutoring_user')->count());
            $this->command->info('   - Lehrer: ~' . User::role('teacher')->count());
            $this->command->info('   - Fächer: ' . TutoringSubject::count());
            $this->command->info('   - Tutoring Angebote: ' . TutoringOffer::count());
            $this->command->info('');
            $this->command->info('🔑 Login als Super-Admin:');
            $this->command->info('   Email: kron@naturwelt.at');
            $this->command->info('   Passwort: password');
        } catch (\Exception $e) {
            $this->command->error('❌ Kritischer Fehler beim Seeding: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Erstelle notwendige Rollen
     */
    private function createRoles(): void
    {
        $roles = ['tutoring_user', 'teacher', 'super_admin'];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web']
            );
        }
    }

    /**
     * Erstelle Super-Admin für eine Schule (kron@naturwelt.at)
     */
    private function createSuperAdmin(School $school): void
    {
        // Prüfe ob kron@naturwelt.at für diese Schule bereits existiert
        $existingUser = User::where('email', 'kron@naturwelt.at')
            ->where('school_id', $school->id)
            ->first();

        if ($existingUser) {
            // User existiert bereits, stelle sicher dass er super_admin ist
            if (!$existingUser->hasRole('super_admin')) {
                $existingUser->assignRole('super_admin');
            }
            return;
        }

        // Erstelle neuen Super-Admin
        $superAdmin = User::create([
            'school_id' => $school->id,
            'email' => 'kron@naturwelt.at',
            'password' => $this->hashedPassword,
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'is_active' => 1,
            'confirmed_at' => now(),
            'email_verified_at' => now(),
        ]);

        $superAdmin->assignRole('super_admin');
    }

    /**
     * Erstelle 100 österreichische Schulen
     */
    private function createSchools(): array
    {
        $schools = [];

        foreach ($this->austrianSchools as $schoolData) {
            $school = School::create([
                'short_name' => $schoolData['short_name'],
                'long_name' => $schoolData['long_name'],
                'email' => 'office@' . $schoolData['domain'] . '.at',
                'logo' => null,
                'is_selectable' => true,
            ]);

            // Erstelle sofort den Super-Admin für diese Schule
            $this->createSuperAdmin($school);

            // Füge Nachhilfetool Lizenz hinzu (ID: 2) mit Gültigkeit bis 2026-07-10
            $school->licences()->attach(2, ['valid_until' => '2026-07-10']);

            // Erstelle SchoolTool Record
            SchoolTool::create([
                'school_id' => $school->id,
                'tutoring_student_must_be_confirmed' => rand(1, 10) <= 8 ? 1 : 0, // 80% müssen bestätigt werden
                'tutoring_confirmer_email' => 'kron@naturwelt.at',
                'tutoring_max_offers_per_student' => rand(3, 5), // 3-5 Angebote pro Student
            ]);

            $schools[] = $school;
        }

        return $schools;
    }

    /**
     * Erstelle Fächer für eine Schule
     */
    private function createSubjects(School $school): array
    {
        $subjectModels = [];

        foreach ($this->subjects as $subject) {
            // Wähle 2-4 zufällige Lehrer-Emails für Mentoren
            $mentorCount = rand(2, 4);
            $mentors = [];
            for ($i = 0; $i < $mentorCount; $i++) {
                $lastName = $this->lastNames[array_rand($this->lastNames)];
                $firstName = $this->firstNames['männlich'][array_rand($this->firstNames['männlich'])];
                $mentors[] = strtolower($firstName . '.' . $lastName) . '@' . $this->getSchoolDomain($school) . '.at';
            }

            $subjectModel = TutoringSubject::create([
                'school_id' => $school->id,
                'short_name' => $subject['short'],
                'long_name' => $subject['long'],
                'must_be_accepted' => rand(0, 10) > 3, // 70% müssen bestätigt werden
                'email_mentors' => $mentors,
            ]);

            $subjectModels[] = $subjectModel;
        }

        return $subjectModels;
    }

    /**
     * Erstelle Lehrer für eine Schule
     */
    private function createTeachers(School $school, int $count): array
    {
        $teachers = [];
        $domain = $this->getSchoolDomain($school);
        $usedEmails = []; // Track verwendete Emails

        for ($i = 0; $i < $count; $i++) {
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

            // Generiere eindeutige Email (nur lokale Prüfung für Performance)
            $baseEmail = strtolower($firstName . '.' . $lastName);
            $emailCounter = $i + 10000; // Start höher um Konflikte mit Schülern zu vermeiden
            $email = $baseEmail . $emailCounter . '@' . $domain . '.at';

            // Falls Email bereits existiert, erhöhe Counter bis eindeutig
            while (in_array($email, $usedEmails)) {
                $emailCounter++;
                $email = $baseEmail . $emailCounter . '@' . $domain . '.at';
            }

            $usedEmails[] = $email;

            // Generiere Lehrer-Kürzel (3-4 Zeichen)
            $shortLength = rand(3, 4);
            $short = strtoupper(substr($lastName, 0, $shortLength));

            $teacher = User::create([
                'school_id' => $school->id,
                'email' => $email,
                'password' => $this->hashedPassword,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'short' => $short,
                'sex' => $sex,
                'is_active' => 1,
                'confirmed_at' => now(),
                'email_verified_at' => now(),
            ]);

            $teacher->assignRole('teacher');
            $teachers[] = $teacher;
        }

        return $teachers;
    }

    /**
     * Erstelle Schüler für eine Schule
     */
    private function createStudents(School $school, int $count): array
    {
        $students = [];
        $domain = $this->getSchoolDomain($school);
        $usedEmails = []; // Track verwendete Emails

        for ($i = 0; $i < $count; $i++) {
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

            // Generiere eindeutige Email (nur lokale Prüfung für Performance)
            $baseEmail = strtolower($firstName . '.' . $lastName);
            $emailCounter = $i;
            $email = $baseEmail . $emailCounter . '@' . $domain . '.at';

            // Falls Email bereits existiert, erhöhe Counter bis eindeutig
            while (in_array($email, $usedEmails)) {
                $emailCounter++;
                $email = $baseEmail . $emailCounter . '@' . $domain . '.at';
            }

            $usedEmails[] = $email;

            $student = User::create([
                'school_id' => $school->id,
                'email' => $email,
                'password' => $this->hashedPassword,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'sex' => $sex,
                'is_active' => 1,
                'confirmed_at' => now(),
                'email_verified_at' => now(),
            ]);

            $student->assignRole('tutoring_user');
            $students[] = $student;

            // Fortschrittsanzeige alle 100 Schüler
            if (($i + 1) % 100 === 0) {
                $current = $i + 1;
                $this->command->info("  ↳ {$current}/{$count} Schüler erstellt...");
            }
        }

        return $students;
    }

    /**
     * Erstelle Tutoring Angebote
     */
    private function createTutoringOffers(School $school, array $students, array $subjects, int $count): void
    {
        // Wähle zufällig 200 Schüler aus
        shuffle($students);
        $selectedStudents = array_slice($students, 0, $count);

        foreach ($selectedStudents as $index => $student) {
            $subject = $subjects[array_rand($subjects)];

            // Bestimme Status des Angebots
            $statusRand = rand(1, 100);
            if ($statusRand <= 70) {
                // 70%: bestätigt und online
                $isConfirmed = true;
                $isActive = true;
            } elseif ($statusRand <= 85) {
                // 15%: bestätigt aber nicht online
                $isConfirmed = true;
                $isActive = false;
            } else {
                // 15%: nicht bestätigt
                $isConfirmed = false;
                $isActive = false;
            }

            // Wähle 1-3 zufällige Klassen
            $numClasses = rand(1, 3);
            $shuffledClasses = $this->classes;
            shuffle($shuffledClasses);
            $selectedClasses = array_slice($shuffledClasses, 0, $numClasses);

            // Erstelle Zeitplan
            $timeTable = $this->generateTimeTable();

            // Mentor Email von Subject
            $mentorEmail = null;
            if ($subject->email_mentors && count($subject->email_mentors) > 0) {
                $mentorEmail = $subject->email_mentors[array_rand($subject->email_mentors)];
            }

            // Wenn must_be_accepted == false, dann email_mentor = null und accepted_at = now()
            if (!$subject->must_be_accepted) {
                $mentorEmail = null;
                $acceptedAt = now()->format('Y-m-d');
            } else {
                $acceptedAt = $isConfirmed ? now()->subDays(rand(1, 30))->format('Y-m-d') : null;
            }

            TutoringOffer::create([
                'school_id' => $school->id,
                'user_id' => $student->id,
                'subject_id' => $subject->id,
                'title' => $subject->long_name . ' Nachhilfe',
                'description' => 'Ich biete professionelle Nachhilfe in ' . $subject->long_name . ' an. Langjährige Erfahrung und gute Noten garantiert!',
                'classes' => $selectedClasses,
                'time_table' => $timeTable,
                'active_until' => now()->addMonths(rand(1, 6))->format('Y-m-d'),
                'is_active' => $isActive,
                'price_per_hour' => rand(10, 25),
                'is_group' => rand(0, 10) > 7, // 30% Gruppenunterricht
                'max_group_members' => rand(0, 10) > 7 ? rand(2, 5) : null,
                'must_be_accepted' => $subject->must_be_accepted,
                'email_mentor' => $mentorEmail,
                'accepted_at' => $acceptedAt,
                'click_count' => rand(0, 50),
            ]);

            // Fortschrittsanzeige alle 50 Angebote
            if (($index + 1) % 50 === 0) {
                $current = $index + 1;
                $this->command->info("  ↳ {$current}/{$count} Angebote erstellt...");
            }
        }
    }

    /**
     * Generiere einen zufälligen Zeitplan
     */
    private function generateTimeTable(): array
    {
        $timeTable = [];
        $numDays = rand(2, 4); // 2-4 Tage pro Woche verfügbar
        $shuffledDays = $this->weekdays;
        shuffle($shuffledDays);
        $selectedDays = array_slice($shuffledDays, 0, $numDays);

        foreach ($selectedDays as $day) {
            $timeTable[] = [
                'day' => $day,
                'from' => sprintf('%02d:00', rand(14, 17)),
                'to' => sprintf('%02d:00', rand(16, 19)),
            ];
        }

        return $timeTable;
    }

    /**
     * Extrahiere Domain aus Schul-Daten
     */
    private function getSchoolDomain(School $school): string
    {
        foreach ($this->austrianSchools as $schoolData) {
            if ($schoolData['short_name'] === $school->short_name) {
                return $schoolData['domain'];
            }
        }

        return 'school';
    }
}
