<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates a timetable v3 test summary pdf from the completed run', function () {
    Pdf::fake();

    $user = createStudentTimetableV3TestSummaryPdfUser();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/tests-v3/summary/pdf', [
            'results' => [
                [
                    'status' => 'passed',
                    'class_label' => '1A',
                    'student_name' => 'Auer Anna',
                    'message' => '',
                ],
                [
                    'status' => 'passed',
                    'class_label' => '1A',
                    'student_name' => 'Berger Ben',
                    'message' => '',
                ],
                [
                    'status' => 'failed',
                    'class_label' => '1B',
                    'student_name' => 'Celik Cem',
                    'message' => 'Abweichungen: Aktuelle, Zusätzliche',
                ],
                [
                    'status' => 'invalid_data',
                    'class_label' => '1C',
                    'student_name' => 'Dorner Dora',
                    'message' => 'Semester fehlt.',
                ],
            ],
        ])
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(function ($pdf): bool {
        return $pdf->viewName === 'pdfs.student-timetable-v3-test-summary'
            && $pdf->downloadName === 'stundenplan-v3-testzusammenfassung.pdf'
            && $pdf->isDownload()
            && $pdf->contains('Testzusammenfassung Stundenplan V3')
            && $pdf->contains('Abendgymnasium')
            && $pdf->contains('2026/27')
            && $pdf->contains('1 von 3 Tests sind fehlgeschlagen.')
            && $pdf->contains('<span class="summary-label">Geprüft</span>')
            && $pdf->contains('<span class="summary-value">3</span>')
            && $pdf->contains('Celik Cem')
            && $pdf->contains('Abweichungen: Aktuelle, Zusätzliche')
            && $pdf->contains('Dorner Dora')
            && $pdf->contains('Semester fehlt.')
            && $pdf->contains('border-top: 3mm solid #c2410c;')
            && $pdf->contains('background: #dbeafe;');
    });
});

it('preserves unicode names and removes encoded whitespace from the pdf', function () {
    Pdf::fake();

    $user = createStudentTimetableV3TestSummaryPdfUser();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/tests-v3/summary/pdf', [
            'results' => [
                [
                    'status' => 'failed',
                    'class_label' => '2U&#x20;',
                    'student_name' => 'Gavrilovič Isabella-Majuma &#x20;',
                    'message' => 'Abweichungen: Aktuelle &amp; Zusätzliche',
                ],
            ],
        ])
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(function ($pdf): bool {
        return $pdf->contains('2U')
            && $pdf->contains('Gavrilovič Isabella-Majuma')
            && $pdf->contains('Abweichungen: Aktuelle &amp; Zusätzliche')
            && $pdf->contains('font-family: "DejaVu Sans", sans-serif;')
            && ! $pdf->contains('&#x20;')
            && ! $pdf->contains('&amp;#x20;');
    });
});

it('validates timetable v3 test summary pdf results', function () {
    $user = createStudentTimetableV3TestSummaryPdfUser();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/tests-v3/summary/pdf', [
            'results' => [
                [
                    'status' => 'unknown',
                    'class_label' => '1A',
                    'student_name' => 'Auer Anna',
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('results.0.status');
});

it('requires authentication for the timetable v3 test summary pdf', function () {
    $this->postJson('/api/admin/students-timetables/tests-v3/summary/pdf', [
        'results' => [
            [
                'status' => 'passed',
                'class_label' => '1A',
                'student_name' => 'Auer Anna',
            ],
        ],
    ])->assertUnauthorized();
});

it('forbids timetable moderators from Tests V3 actions', function () {
    $user = createStudentTimetableV3TestSummaryPdfUser('studentstimetables_moderator');

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/timetable-v3/student-information')
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/tests-v3/summary/pdf')
        ->assertForbidden();
});

function createStudentTimetableV3TestSummaryPdfUser(string $roleName = 'studentstimetables_admin'): User
{
    $school = School::factory()->create([
        'long_name' => 'Abendgymnasium',
        'short_name' => 'abendgym',
    ]);
    $schoolTool = SchoolTool::factory()->create([
        'school_id' => $school->id,
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => true,
        'students_timetables_user_test_mode' => false,
        'students_timetables_user_comming_soon' => false,
    ]);
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
        'concerns' => '2026/27',
    ]);

    $schoolTool->forceFill(['active_schoolyear_id' => $schoolyear->id])->save();

    $licence = Licence::query()->create([
        'name' => 'StudentsTimetables',
        'long_name' => 'Tool zum Verwalten von Schülerstundenplänen',
        'price_per_year' => 200,
    ]);

    SchoolLicence::query()->create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
    ]);

    Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $user->assignRole($roleName);

    return $user;
}
