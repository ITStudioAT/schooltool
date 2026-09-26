<?php

use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** @param list<array{class: string, name: string, login: string, email: string}> $rows */
function studentEmailTestZip(array $rows, ?string $secondHtml = null): UploadedFile
{
    $header = '<tr><td>Klasse</td><td>Benutzer</td><td>NT-Login</td><td>Passwort</td><td>E-Mail</td></tr>';
    $body = implode('', array_map(static function (array $row): string {
        $cells = [$row['class'], $row['name'], $row['login'], 'ignored-secret', $row['email']];

        return '<tr>'.implode('', array_map(
            static fn (string $cell): string => '<td>'.htmlspecialchars($cell, ENT_QUOTES, 'UTF-8').'</td>',
            $cells,
        )).'</tr>';
    }, $rows));
    $html = "<html><body><table>{$header}{$body}</table></body></html>";

    $path = tempnam(sys_get_temp_dir(), 'student-email-zip-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('Logins_1A.htm', $html);
    $zip->addFromString('Logins_Schueler.htm', $secondHtml ?? $html);
    $zip->close();
    $contents = file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent('logins.zip', $contents);
}

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');
});

test('adds only missing emails to uniquely matched students and linked placeholder accounts', function () {
    $placeholder = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'noemail.123@schooltool.noemail',
    ]);
    $missing = Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A', 'last_name' => 'Müller', 'first_name' => 'Anna Maria',
        'email' => null, 'user_id' => $placeholder->id,
    ]);
    $existing = Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A', 'last_name' => 'Becker', 'first_name' => 'Tim',
        'email' => 'personal@example.test',
    ]);
    $otherSchool = School::factory()->create();
    $foreign = Import116::factory()->forSchool($otherSchool)->importedBy($this->admin)->create([
        'schoolyear_id' => Schoolyear::factory()->create(['school_id' => $otherSchool->id])->id,
        'class' => '1A', 'last_name' => 'Müller', 'first_name' => 'Anna Maria', 'email' => null,
    ]);

    $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/teaching/student-emails/import', [
        'file' => studentEmailTestZip([
            ['class' => '1A', 'name' => 'Müller Anna', 'login' => 'Anna.Mueller', 'email' => 'anna.mueller@cdgym.at'],
            ['class' => '1A', 'name' => 'Becker Tim', 'login' => 'Tim.Becker', 'email' => 'tim.becker@cdgym.at'],
            ['class' => '1A', 'name' => 'Nobody Here', 'login' => 'Here.Nobody', 'email' => 'here.nobody@cdgym.at'],
        ]),
    ])->assertOk()->assertJsonPath('total', 3)
        ->assertJsonPath('matched', 2)
        ->assertJsonPath('updated', 1)
        ->assertJsonPath('skipped_existing', 1)
        ->assertJsonPath('unmatched', 1);

    expect($missing->fresh()->email)->toBe('anna.mueller@cdgym.at')
        ->and($placeholder->fresh()->email)->toBe('anna.mueller@cdgym.at')
        ->and($existing->fresh()->email)->toBe('personal@example.test')
        ->and($foreign->fresh()->email)->toBeNull();
});

test('rejects a malformed list before changing any student', function () {
    $student = Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A', 'last_name' => 'Meyer', 'first_name' => 'Eva', 'email' => null,
    ]);

    $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/teaching/student-emails/import', [
        'file' => studentEmailTestZip(
            [['class' => '1A', 'name' => 'Meyer Eva', 'login' => 'Eva.Meyer', 'email' => 'eva.meyer@cdgym.at']],
            '<table><tr><td>Unexpected</td></tr></table>',
        ),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');

    expect($student->fresh()->email)->toBeNull();
});

test('skips ambiguous names and account email collisions', function () {
    $firstAmbiguous = Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A', 'last_name' => 'Klein', 'first_name' => 'Max', 'email' => null,
    ]);
    $secondAmbiguous = Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A', 'last_name' => 'Klein', 'first_name' => 'Max', 'email' => null,
    ]);
    $placeholder = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'noemail.456@schooltool.noemail',
    ]);
    $collision = Import116::factory()->forSchool($this->school)->importedBy($this->admin)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A', 'last_name' => 'Huber', 'first_name' => 'Lena',
        'email' => null, 'user_id' => $placeholder->id,
    ]);
    User::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'lena.huber@cdgym.at',
    ]);

    $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/teaching/student-emails/import', [
        'file' => studentEmailTestZip([
            ['class' => '1A', 'name' => 'Klein Max', 'login' => 'Max.Klein', 'email' => 'max.klein@cdgym.at'],
            ['class' => '1A', 'name' => 'Huber Lena', 'login' => 'Lena.Huber', 'email' => 'lena.huber@cdgym.at'],
        ]),
    ])->assertOk()->assertJsonPath('matched', 1)
        ->assertJsonPath('updated', 0)
        ->assertJsonPath('ambiguous', 1)
        ->assertJsonPath('unmatched', 1)
        ->assertJsonPath('skipped_conflict', 1);

    expect($firstAmbiguous->fresh()->email)->toBeNull()
        ->and($secondAmbiguous->fresh()->email)->toBeNull()
        ->and($collision->fresh()->email)->toBeNull()
        ->and($placeholder->fresh()->email)->toBe('noemail.456@schooltool.noemail');
});

test('does not let teachers run the email import', function () {
    $teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $teacher->assignRole('teacher');

    $this->actingAs($teacher, 'sanctum')->postJson('/api/admin/teaching/student-emails/import', [
        'file' => studentEmailTestZip([
            ['class' => '1A', 'name' => 'Meyer Eva', 'login' => 'Eva.Meyer', 'email' => 'eva.meyer@cdgym.at'],
        ]),
    ])->assertForbidden();
});
