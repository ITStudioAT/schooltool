<?php

namespace App\Services;

use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\MultipleChoice\McTestService;
use Illuminate\Support\Facades\Schema;

class TeachingWorkMultipleChoice
{
    public function __construct(private McTestService $tests) {}

    /** @return array<string, mixed> */
    public function options(TeachingCourseWork $work, User $actor, ?string $date): array
    {
        if (! Schema::hasTable('mc_tests') || ! Schema::hasTable('mc_catalogs') || ! Schema::hasTable('mc_attempts')) {
            return ['available' => false, 'tests' => [], 'catalogs' => [], 'linked_test_id' => null];
        }
        $tests = McTest::query()->where('school_id', $work->teachingCourse->school_id)->where('owner_id', $actor->id)
            ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->orderBy('title')
            ->get(['id', 'catalog_id', 'title', 'status', 'question_count', 'total_points', 'duration_seconds']);
        $catalogs = McCatalog::query()->where('school_id', $work->teachingCourse->school_id)->where('owner_id', $actor->id)
            ->orderBy('title')->get(['id', 'title', 'questions'])->map(function (McCatalog $catalog): array {
                $count = count(array_filter($catalog->questions ?? [], fn (mixed $question): bool => is_array($question)
                    && ($question['active'] ?? false) === true && ($question['reviewed'] ?? false) === true));

                return ['id' => $catalog->id, 'title' => $catalog->title, 'reviewed_question_count' => $count];
            });
        $binding = $work->status['multiple_choice_tests'][$this->slot($work, $date)] ?? null;

        return ['available' => true,
            'tests' => $tests->map(fn (McTest $test): array => [...$test->toArray(),
                'catalog_title' => $catalogs->firstWhere('id', $test->catalog_id)['title'] ?? null]),
            'catalogs' => $catalogs->filter(fn (array $catalog): bool => $catalog['reviewed_question_count'] >= 10)->values(),
            'linked_test_id' => ($binding['work_id'] ?? null) === $work->id && $tests->contains('id', $binding['test_id'] ?? null)
                ? $binding['test_id'] : null];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $participants
     * @return array<string, mixed>
     */
    public function prepare(TeachingCourseWork $work, User $actor, array $data, array $participants): array
    {
        $slot = $this->slot($work, $data['date'] ?? null);
        $binding = $work->status['multiple_choice_tests'][$slot] ?? null;
        $requestHash = hash('sha256', json_encode([$data, array_column($participants, 'schooltool_person_id')], JSON_THROW_ON_ERROR));
        $repeated = ($binding['request_id'] ?? null) === $data['request_id'] && ($binding['request_hash'] ?? null) === $requestHash;
        abort_unless($repeated || ($binding['test_id'] ?? null) === ($data['expected_test_id'] ?? null), 409,
            'Die MC-Verknüpfung wurde inzwischen geändert. Bitte den Dialog erneut öffnen.');
        $studentIds = array_column($participants, 'schooltool_person_id');
        abort_unless(count($studentIds) === count($participants) && ! in_array(null, $studentIds, true), 422,
            'Für den MC-Test benötigen alle Prüfungskandidaten ein zugeordnetes Schülerkonto.');
        $studentIds = array_map('intval', $studentIds);
        if ($data['mc_mode'] === 'existing' || $repeated) {
            $test = McTest::query()->where('school_id', $work->teachingCourse->school_id)->where('owner_id', $actor->id)
                ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)
                ->lockForUpdate()->findOrFail($repeated ? $binding['test_id'] : $data['test_id']);
            if ($test->snapshot === null && ! $test->attempts()->exists() && $test->status === 'draft') {
                $test = $this->tests->prepare($test, $studentIds);
            }
            $assigned = $test->attempts()->pluck('student_id')->map(fn (mixed $id): int => (int) $id)->all();
            abort_unless(array_diff($studentIds, $assigned) === [], 422,
                'Der ausgewählte MC-Test enthält nicht alle Prüfungskandidaten dieser Leistungsfeststellung.');
        } else {
            $catalog = McCatalog::query()->where('school_id', $work->teachingCourse->school_id)->where('owner_id', $actor->id)
                ->lockForUpdate()->findOrFail($data['catalog_id']);
            $test = McTest::query()->create([
                'school_id' => $work->teachingCourse->school_id, 'schoolyear_id' => $work->teachingCourse->schoolyear_id,
                'owner_id' => $actor->id, 'catalog_id' => $catalog->id, 'title' => mb_substr($work->title.' – MC', 0, 255),
                'status' => 'draft', 'question_count' => 10, 'total_points' => $data['total_points'],
                'duration_seconds' => $data['duration_minutes'] * 60, 'lead_seconds' => 60,
                'start_mode' => 'common', 'exam_mode' => false, 'balance_types' => true,
                'shuffle_questions' => true, 'shuffle_options' => true,
            ]);
            $test = $this->tests->prepare($test, $studentIds);
        }
        $status = $work->status ?? [];
        $status['multiple_choice_tests'][$slot] = ['work_id' => $work->id, 'test_id' => $test->id,
            'request_id' => $data['request_id'], 'request_hash' => $requestHash];
        $work->status = $status;
        $work->save();

        return ['test_id' => $test->id, 'catalog_id' => $test->catalog_id, 'title' => $test->title,
            'status' => $test->status, 'question_count' => $test->question_count,
            'total_points' => $test->total_points, 'duration_seconds' => $test->duration_seconds,
            'question_pool' => $test->snapshot['questions'] ?? [],
            'access_links_ready' => $test->status === 'published' && $test->access_links_disabled_at === null,
            'exercise_export_url' => $test->status === 'published' && $test->access_links_disabled_at === null
                ? route('admin.multiple-choice.tests.exercise-export', ['test' => $test->id]) : null];
    }

    private function slot(TeachingCourseWork $work, ?string $date): string
    {
        return $date ?? $work->date_for_all_groups?->format('Y-m-d') ?? 'all';
    }
}
