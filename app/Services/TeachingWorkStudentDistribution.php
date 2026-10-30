<?php

namespace App\Services;

use App\Jobs\FinalizeMultipleChoiceTest;
use App\Models\McAttempt;
use App\Models\McTest;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\MultipleChoice\McTestService;
use App\Support\SchooltoolAssessmentJson;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeachingWorkStudentDistribution
{
    /** @return list<array<string, mixed>> */
    public function mcReleasePreview(TeachingCourseWork $work): array
    {
        return array_map(fn (array $item): array => $this->mcPreviewItem($work, $item), $this->mcTests($work));
    }

    public function publishMcTests(TeachingCourseWork $work, ?string $selection): void
    {
        $items = $this->mcTests($work, true);
        if ($items === []) {
            return;
        }
        try {
            $decoded = json_decode($selection ?? '', true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['mc_tests' => 'Bitte die MC-Freigabe zuerst auswählen.']);
        }
        $data = Validator::make(['mc_tests' => $decoded], [
            'mc_tests' => ['required', 'array', 'min:1'],
            'mc_tests.*.id' => ['required', 'integer', 'distinct'],
            'mc_tests.*.expected_version' => ['required', 'string', 'size:64'],
            'mc_tests.*.start_mode' => ['required', 'in:individual,common'],
        ])->validate()['mc_tests'];
        $choices = collect($data)->keyBy(fn (array $entry): int => (int) $entry['id']);
        if ($choices->count() !== count($items) || $choices->keys()->sort()->values()->all() !== collect($items)->map(fn (array $item): int => (int) $item['test']->id)->sort()->values()->all()) {
            throw ValidationException::withMessages(['mc_tests' => 'Die MC-Zuordnung hat sich geändert. Bitte den Dialog erneut öffnen.']);
        }
        foreach ($items as $item) {
            $test = $item['test'];
            $choice = $choices->get((int) $test->id);
            abort_unless(hash_equals($this->mcVersion($item), $choice['expected_version']), 409, 'Der MC-Test wurde inzwischen geändert. Bitte den Dialog erneut öffnen.');
            $this->assertMcReady($test, $item['attempts']);
            if ($test->status !== 'draft' && $choice['start_mode'] !== $test->start_mode) {
                throw ValidationException::withMessages(['mc_tests' => 'Die Startart eines bereits freigegebenen Tests kann hier nicht geändert werden.']);
            }
            if ($test->status === 'draft') {
                $this->configureMcStart($work, $test, $item['attempts'], $choice['start_mode']);
            }
            if ($test->status === 'published') {
                continue;
            }
            $wasDraft = $test->status === 'draft';
            $published = app(McTestService::class)->publish($test, []);
            if ($wasDraft && $published->start_mode === 'individual' && $published->closes_at !== null) {
                FinalizeMultipleChoiceTest::dispatch((int) $published->id)->delay($published->closes_at)->afterCommit();
            }
        }
    }

    /** @return list<array{test: McTest, attempts: Collection<int, McAttempt>}> */
    private function mcTests(TeachingCourseWork $work, bool $lock = false): array
    {
        $bindings = $work->status['multiple_choice_tests'] ?? [];
        if (! is_array($bindings)) {
            throw ValidationException::withMessages(['mc_tests' => 'Die MC-Zuordnung ist unvollständig. Bitte die Arbeit prüfen.']);
        }
        if ($lock) {
            $work->teachingCourse->teachingCourseStudentsWithTrashed()->orderBy('id')->lockForUpdate()->get(['id']);
        }
        $active = $this->recipients($work, $lock);
        $expected = [];
        foreach ($bindings as $slot => $binding) {
            if (! is_array($binding) || (int) ($binding['work_id'] ?? 0) !== (int) $work->id || ! is_numeric($binding['test_id'] ?? null)) {
                throw ValidationException::withMessages(['mc_tests' => 'Die MC-Zuordnung ist unvollständig. Bitte die Arbeit prüfen.']);
            }
            $testId = (int) $binding['test_id'];
            foreach ($work->groups ?? [] as $group) {
                $date = $group['date'] ?? $work->date_for_all_groups?->format('Y-m-d');
                if ($slot !== 'all' && $slot !== $date) {
                    continue;
                }
                foreach ($group['student_ids'] ?? [] as $studentId) {
                    if (in_array((int) $studentId, $active, true)) {
                        $expected[$testId][(int) $studentId] = (int) $studentId;
                    }
                }
            }
            if (empty($expected[$testId])) {
                throw ValidationException::withMessages(['mc_tests' => 'Dem verknüpften MC-Test sind für diese Arbeit keine aktiven Schüler:innen zugeordnet.']);
            }
        }
        ksort($expected, SORT_NUMERIC);
        $items = [];
        foreach ($expected as $id => $studentIds) {
            $query = McTest::query()->where('school_id', $work->teachingCourse->school_id)
                ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where('owner_id', $work->teachingCourse->user_id);
            $test = ($lock ? $query->lockForUpdate() : $query)->find($id);
            if (! $test) {
                throw ValidationException::withMessages(['mc_tests' => 'Der verknüpfte MC-Test gehört nicht eindeutig zu dieser Arbeit.']);
            }
            $attemptQuery = $test->attempts()->orderBy('id');
            $attempts = ($lock ? $attemptQuery->lockForUpdate() : $attemptQuery)->get();
            $assigned = $attempts->pluck('student_id')->map(fn (mixed $studentId): int => (int) $studentId)->sort()->values()->all();
            $required = array_values($studentIds);
            sort($required, SORT_NUMERIC);
            if ($assigned !== $required) {
                throw ValidationException::withMessages(['mc_tests' => 'Die Teilnehmenden des MC-Tests stimmen nicht genau mit der zugeordneten Gruppe überein. Bitte die Zuordnung prüfen.']);
            }
            $studentQuery = User::query()->whereIn('id', $required)->where('school_id', $work->teachingCourse->school_id)
                ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where(fn ($query) => $query->where('is_active', true)->orWhereNull('is_active'))
                ->role('student')->orderBy('id');
            if (($lock ? $studentQuery->lockForUpdate() : $studentQuery)->get(['id'])->count() !== count($required)) {
                throw ValidationException::withMessages(['mc_tests' => 'Nicht alle Teilnehmenden haben ein aktives Schülerkonto dieser Schule und dieses Schuljahres.']);
            }
            $this->assertMcReady($test, $attempts);
            if ($test->status !== 'published') {
                $this->assertMcNotShared($work, $test);
            }
            $items[] = ['test' => $test, 'attempts' => $attempts];
        }

        return $items;
    }

    private function assertMcNotShared(TeachingCourseWork $work, McTest $test): void
    {
        $shared = TeachingCourseWork::query()->whereKeyNot($work->id)
            ->whereHas('teachingCourse', fn ($query) => $query->where('school_id', $work->teachingCourse->school_id)
                ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where('user_id', $work->teachingCourse->user_id))
            ->where(function ($query) use ($test): void {
                $query->whereRaw('JSON_CONTAINS(JSON_EXTRACT(status, ?), ?)', ['$.multiple_choice_tests.*.test_id', json_encode((int) $test->id, JSON_THROW_ON_ERROR)])
                    ->orWhereRaw('JSON_CONTAINS(JSON_EXTRACT(status, ?), ?)', ['$.multiple_choice_tests.*.test_id', json_encode((string) $test->id, JSON_THROW_ON_ERROR)]);
            })->exists();
        if ($shared) {
            throw ValidationException::withMessages(['mc_tests' => 'Dieser MC-Test ist auch mit einer anderen Arbeit verknüpft. Bitte die Zuordnung vor der gemeinsamen Freigabe prüfen.']);
        }
    }

    /** @param array{test: McTest, attempts: Collection<int, McAttempt>} $item
     * @return array<string, mixed>
     */
    private function mcPreviewItem(TeachingCourseWork $work, array $item): array
    {
        $test = $item['test'];

        return ['id' => $test->id, 'title' => $test->title, 'status' => $test->status, 'start_mode' => $test->start_mode,
            'duration_seconds' => $test->duration_seconds, 'closes_at' => $test->closes_at?->toIso8601String(),
            'individual_closes_at' => $this->individualMcDeadline($work, $test)?->toIso8601String(),
            'starts_at' => $test->starts_at?->toIso8601String(), 'paused_at' => $test->paused_at?->toIso8601String(),
            'mode_editable' => $test->status === 'draft' && ! $this->mcStarted($test, $item['attempts']),
            'version' => $this->mcVersion($item)];
    }

    /** @param array{test: McTest, attempts: Collection<int, McAttempt>} $item */
    private function mcVersion(array $item): string
    {
        return SchooltoolAssessmentJson::digest(['test' => $item['test']->getRawOriginal(),
            'attempts' => $item['attempts']->map(fn (McAttempt $attempt): array => $attempt->getRawOriginal())->all()]);
    }

    /** @param Collection<int, McAttempt> $attempts */
    private function assertMcReady(McTest $test, $attempts): void
    {
        if (! in_array($test->status, ['draft', 'published', 'withdrawn'], true) || ! in_array($test->start_mode, ['individual', 'common'], true)
            || $test->snapshot === null || $attempts->isEmpty() || $attempts->contains(fn (McAttempt $attempt): bool => ! filled($attempt->access_token))
            || $test->access_links_disabled_at !== null) {
            throw ValidationException::withMessages(['mc_tests' => 'Der verknüpfte MC-Test ist nicht bereit oder seine persönlichen Zugänge sind deaktiviert. Bitte den Test prüfen.']);
        }
    }

    /** @param Collection<int, McAttempt> $attempts */
    private function mcStarted(McTest $test, $attempts): bool
    {
        return $test->starts_at !== null || $attempts->contains(fn (McAttempt $attempt): bool => $attempt->started_at !== null
            || $attempt->completed_at !== null || ($attempt->answers ?? []) !== []);
    }

    private function individualMcDeadline(TeachingCourseWork $work, McTest $test): ?CarbonImmutable
    {
        if ($test->start_mode === 'individual' && $test->closes_at !== null) {
            return CarbonImmutable::instance($test->closes_at);
        }
        if ($work->finish_until_date === null) {
            return null;
        }

        return CarbonImmutable::parse($work->finish_until_date->format('Y-m-d').' '.($work->finish_until_time ?: '23:59:00'), 'Europe/Vienna');
    }

    /** @param Collection<int, McAttempt> $attempts */
    private function configureMcStart(TeachingCourseWork $work, McTest $test, $attempts, string $mode): void
    {
        $deadline = $mode === 'individual' ? $this->individualMcDeadline($work, $test) : null;
        if ($mode === 'individual' && (! $deadline || ! $deadline->isFuture())) {
            throw ValidationException::withMessages(['mc_tests' => 'Für den individuellen MC-Test ist eine zukünftige Schlussfrist erforderlich. Bitte die Frist prüfen.']);
        }
        if ($this->mcStarted($test, $attempts) && ($test->start_mode !== $mode || ($mode === 'individual' && ! $test->closes_at?->equalTo($deadline)))) {
            throw ValidationException::withMessages(['mc_tests' => 'Dieser MC-Test wurde bereits begonnen. Seine Startart und Frist können hier nicht geändert werden.']);
        }
        if ($test->start_mode !== $mode || ($mode === 'individual' && ! $test->closes_at?->equalTo($deadline))) {
            $snapshot = $test->snapshot;
            $snapshot['start_mode'] = $mode;
            $snapshot['closes_at'] = $deadline?->toIso8601String();
            $test->forceFill(['start_mode' => $mode, 'closes_at' => $deadline, 'snapshot' => $snapshot])->save();
        }
    }

    public function version(TeachingCourseWork $work): string
    {
        $attributes = $work->getRawOriginal();
        foreach (['groups', 'status'] as $field) {
            $attributes[$field] = $work->getAttribute($field);
        }

        return SchooltoolAssessmentJson::digest($attributes);
    }

    /** @return list<int> */
    public function recipients(TeachingCourseWork $work, bool $lock = false): array
    {
        $assigned = collect($work->groups ?? [])->flatMap(fn (array $group): array => $group['student_ids'] ?? [])->map(fn (mixed $id): int => (int) $id)->unique();

        $query = $work->teachingCourse->teachingCourseStudents()->whereNull('canceled_at')->whereIn('user_id', $assigned);

        return ($lock ? $query->lockForUpdate() : $query)->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    public function visibleTo(TeachingCourseWork $work, User $student): bool
    {
        $release = $work->status['student_distribution'] ?? [];

        return ! empty($release['published_at']) && empty($release['withdrawn_at'])
            && in_array((int) $student->id, $release['student_ids'] ?? [], true)
            && collect($work->groups ?? [])->contains(fn (array $group): bool => in_array((int) $student->id, array_map('intval', $group['student_ids'] ?? []), true));
    }

    /** @param list<UploadedFile> $uploads
     * @param  list<string>  $createdPaths
     * @param  list<string>  $retainedFileIds
     */
    public function publish(TeachingCourseWork $work, string $instructions, array $uploads, array &$createdPaths, array $retainedFileIds = []): void
    {
        $recipients = $this->recipients($work, true);
        if ($recipients === []) {
            throw ValidationException::withMessages(['instructions' => 'Der Arbeit sind keine aktiven Schüler:innen zugeordnet.']);
        }
        $files = $work->status['student_task_files'] ?? [];
        $activeFiles = array_values(array_filter($files, fn (array $file): bool => in_array($file['id'], $retainedFileIds, true)));
        if (count($activeFiles) !== count(array_unique($retainedFileIds))) {
            throw ValidationException::withMessages(['retained_file_ids' => 'Ein Aufgaben-PDF gehört nicht zu dieser Arbeit.']);
        }
        if (trim($instructions) === '' && $uploads === [] && $activeFiles === []) {
            throw ValidationException::withMessages(['instructions' => 'Bitte einen Aufgabentext oder ein Aufgaben-PDF bereitstellen.']);
        }
        if (count($activeFiles) + count($uploads) > 5) {
            throw ValidationException::withMessages(['files' => 'Höchstens fünf Aufgaben-PDFs pro Arbeit.']);
        }
        foreach ($uploads as $upload) {
            if (! str_starts_with($upload->get(), '%PDF-')) {
                throw ValidationException::withMessages(['files' => 'Bitte ausschließlich gültige Aufgaben-PDFs hochladen.']);
            }
            $path = 'teaching/work_tasks/'.$work->teachingCourse->school_id.'/'.$work->id.'/'.Str::uuid().'.pdf';
            $createdPaths[] = $path;
            if (! Storage::disk('local')->put($path, $upload->get())) {
                throw ValidationException::withMessages(['files' => 'Das Aufgaben-PDF konnte nicht gespeichert werden.']);
            }
            $files[] = ['id' => (string) Str::uuid(), 'name' => basename(str_replace('\\', '/', $upload->getClientOriginalName())),
                'file_path' => $path, 'storage_disk' => 'local', 'sha256' => hash_file('sha256', $upload->getRealPath())];
            $activeFiles[] = end($files);
        }
        $work->status = array_merge($work->status ?? [], ['student_task_files' => $files, 'student_distribution' => [
            'published_at' => now()->toIso8601String(), 'withdrawn_at' => null, 'instructions' => trim($instructions), 'student_ids' => $recipients,
            'file_ids' => array_column($activeFiles, 'id'),
        ]]);
        $work->save();
    }

    /** @return array<string, mixed> */
    public function forStudent(TeachingCourseWork $work, User $student): array
    {
        $groups = collect($work->groups ?? [])->filter(fn (array $group): bool => in_array((int) $student->id, array_map('intval', $group['student_ids'] ?? []), true));
        $group = $groups->first();
        $date = $group['date'] ?? $work->date_for_all_groups?->format('Y-m-d');
        $dates = $groups->map(fn (array $group): ?string => $group['date'] ?? $work->date_for_all_groups?->format('Y-m-d'))->filter()->unique()->all();
        $bindings = collect($work->status['multiple_choice_tests'] ?? [])->filter(fn (mixed $binding, mixed $slot): bool => is_array($binding)
            && ($binding['work_id'] ?? null) === $work->id && ($slot === 'all' || in_array($slot, $dates, true)));
        $tests = $bindings->isEmpty() || (int) auth()->id() !== (int) $student->id ? collect() : McTest::query()
            ->whereIn('id', $bindings->pluck('test_id'))->where('school_id', $work->teachingCourse->school_id)
            ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where('owner_id', $work->teachingCourse->user_id)
            ->where('status', 'published')->whereNull('access_links_disabled_at')
            ->with(['attempts' => fn ($query) => $query->where('student_id', $student->id)])->get();

        return ['id' => $work->id, 'title' => $work->title, 'instructions' => $work->status['student_distribution']['instructions'],
            'published_at' => $work->status['student_distribution']['published_at'], 'date' => $date,
            'deadline' => $work->communicatedDeadline() ?? ['date' => $work->finish_until_date?->format('Y-m-d'), 'time' => $work->finish_until_time ?: '23:59'],
            'files' => collect($work->status['student_task_files'] ?? [])->filter(fn (array $file): bool => in_array($file['id'], $work->status['student_distribution']['file_ids'] ?? [], true))->map(fn (array $file): array => ['id' => $file['id'], 'name' => $file['name'],
                'url' => route('student.course-work.task-file', ['courseId' => $work->teaching_course_id, 'course_work' => $work->id, 'fileId' => $file['id']], false)])->values()->all(),
            'tests' => $tests->filter(fn (McTest $test): bool => filled($test->attempts->first()?->access_token))->map(fn (McTest $test): array => [
                'title' => $test->title, 'url' => route('multiple-choice.entry', [], false).'#access='.rawurlencode($test->attempts->first()->access_token),
                'attempt_state' => $this->studentMcAttemptState($test, $test->attempts->first()),
            ])->values()->all()];
    }

    private function studentMcAttemptState(McTest $test, McAttempt $attempt): string
    {
        $deadline = $attempt->effectiveDeadline($test);
        $clock = $test->paused_at ?? now();

        if ($attempt->completed_at !== null || ($deadline !== null && $deadline->lessThanOrEqualTo($clock))) {
            return 'finished';
        }

        if ($test->paused_at !== null) {
            return 'paused';
        }

        if ($attempt->started_at === null) {
            return 'not_started';
        }

        return $deadline === null ? 'unavailable' : 'in_progress';
    }
}
