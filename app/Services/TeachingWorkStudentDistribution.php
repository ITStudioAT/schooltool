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
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

class TeachingWorkStudentDistribution
{
    /** @param list<int>|null $selected */
    public function signalDeadlineChangeAfterCommit(TeachingCourseWork $work, ?array $selected = null): void
    {
        $studentIds = $selected ?? array_unique([...($work->status['student_distribution']['student_ids'] ?? []),
            ...($work->status['student_distribution']['ended_email_student_ids'] ?? [])]);
        $connection = config('broadcasting.connections.'.config('broadcasting.default'), []);
        if (! $studentIds || ! in_array($connection['driver'] ?? null, ['pusher', 'reverb'], true)
            || empty($connection['key']) || empty($connection['secret']) || empty($connection['app_id'])) {
            return;
        }

        DB::afterCommit(function () use ($work, $studentIds, $connection): void {
            defer(function () use ($work, $studentIds, $connection): void {
                rescue(function () use ($work, $studentIds, $connection): void {
                    $channels = collect($this->recipients($work))
                        ->filter(fn (int $id): bool => in_array($id, $studentIds, true))
                        ->map(fn (int $id): PrivateChannel => new PrivateChannel('user.'.$id))
                        ->values()->all();
                    if ($channels === []) {
                        return;
                    }

                    $connection['client_options'] = array_replace($connection['client_options'] ?? [], [
                        'connect_timeout' => 0.5,
                        'timeout' => 1.0,
                    ]);
                    $broadcaster = new PusherBroadcaster(Broadcast::pusher($connection));
                    $broadcaster->broadcast($channels, 'course-work.deadline-changed');
                });
            });
        });
    }

    /** @return list<array<string, mixed>> */
    public function mcReleasePreview(TeachingCourseWork $work, int $actorId): array
    {
        return array_map(fn (array $item): array => $this->mcPreviewItem($work, $item, $actorId), $this->mcTests($work, requireReady: ! $work->studentReleaseEnded()));
    }

    /** @return list<array<string, mixed>> */
    public function runMcManagementAction(TeachingCourseWork $work, int $actorId, int $testId, string $action, string $expectedVersion, bool $confirmed = false): array
    {
        $work->assertStudentReleaseOpen();
        $item = collect($this->mcTests($work, true))->first(fn (array $item): bool => (int) $item['test']->id === $testId);
        abort_unless($item && (int) $item['test']->owner_id === $actorId, 404);
        $test = $item['test'];
        $currentVersion = $action === 'pause' ? $this->mcPauseVersion($test) : $this->mcVersion($item);
        abort_unless(hash_equals($currentVersion, $expectedVersion), 409, 'Der MC-Test wurde inzwischen geändert. Bitte den Stand neu laden.');
        if (in_array($action, ['reset', 'stop', 'delete'], true)) {
            abort_unless($confirmed, 422, 'Bitte bestätige die ausgewählte MC-Aktion.');
            $this->assertMcNotShared($work, $test);
            if (! in_array($action, $this->mcLifecycleActions($work, $item, $actorId), true)) {
                throw ValidationException::withMessages(['action' => 'Diese MC-Aktion ist im aktuellen Zustand nicht verfügbar.']);
            }
            $this->runMcLifecycleAction($work, $item, $action);

            return $this->mcReleasePreview($work, $actorId);
        }
        if ($action !== $this->mcManagementAction($work, $item, $actorId)
            && ! ($action === 'pause' && $test->paused_at === null && $this->canControlWorkTestAttempts($work, $test, $actorId)
                && $this->mcHasOpenAttempts($item))) {
            throw ValidationException::withMessages(['action' => 'Diese MC-Aktion ist im aktuellen Zustand nicht verfügbar.']);
        }

        $service = app(McTestService::class);
        if ($action === 'countdown') {
            $test = $service->startCountdown($item['test']);
            FinalizeMultipleChoiceTest::dispatch($testId)->delay($test->capDeadline($test->ends_at))->afterCommit();
        } elseif ($action === 'pause') {
            $service->pause($item['test'], $actorId);
        } else {
            $test = $service->resume($item['test'], $actorId);
            foreach ($test->attempts()->whereNull('completed_at')->get() as $attempt) {
                if ($deadline = $attempt->effectiveDeadline($test)) {
                    FinalizeMultipleChoiceTest::dispatch($testId)->delay($deadline)->afterCommit();
                }
            }
        }

        return $this->mcReleasePreview($work, $actorId);
    }

    /** @param array{test: McTest, attempts: Collection<int, McAttempt>} $item */
    private function runMcLifecycleAction(TeachingCourseWork $work, array $item, string $action): void
    {
        $service = app(McTestService::class);
        $test = $item['test'];
        if ($action === 'reset') {
            $service->resetWholeTest($test, $item['attempts'], $test->paused_at !== null || $this->taskContentsLocked($work));
        } elseif ($action === 'stop') {
            $service->stopWholeTest($test, $item['attempts']);
        } else {
            $status = $work->status;
            $status['multiple_choice_tests'] = array_filter($status['multiple_choice_tests'], fn (array $binding): bool => (int) $binding['test_id'] !== (int) $test->id);
            if ($status['multiple_choice_tests'] === []) {
                unset($status['multiple_choice_tests']);
            }
            $work->update(['status' => $status]);
            $service->deleteUnlinkedTest($test);
        }
    }

    public function publishMcTests(TeachingCourseWork $work, ?string $selection, bool $releaseNow = false): void
    {
        $work->assertStudentReleaseOpen();
        $items = $this->mcTests($work, true);
        if ($items === []) {
            return;
        }
        abort_if($this->hasLockedPreparation($items), 409,
            'Die Vorbereitung ist gesperrt, weil der gemeinsame MC-Countdown bereits angesetzt wurde.');
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
            'mc_tests.*.adopt_final_deadline' => ['sometimes', 'boolean'],
        ])->validate()['mc_tests'];
        $choices = collect($data)->keyBy(fn (array $entry): int => (int) $entry['id']);
        if ($choices->count() !== count($items) || $choices->keys()->sort()->values()->all() !== collect($items)->map(fn (array $item): int => (int) $item['test']->id)->sort()->values()->all()) {
            throw ValidationException::withMessages(['mc_tests' => 'Die MC-Zuordnung hat sich geändert. Bitte den Dialog erneut öffnen.']);
        }
        foreach ($items as $item) {
            $test = $item['test'];
            $choice = $choices->get((int) $test->id);
            abort_unless(hash_equals($this->mcVersion($item), $choice['expected_version']), 409, 'Der MC-Test wurde inzwischen geändert. Bitte den Dialog erneut öffnen.');
            $this->assertMcReady($test, $item['active_attempts']);
            if ($releaseNow) {
                $test->assertConsistentWorkDeadline();
            }
            $changeMode = $choice['start_mode'] !== $test->start_mode;
            $adopt = (bool) ($choice['adopt_final_deadline'] ?? false);
            if (! $releaseNow || $changeMode || $adopt) {
                $this->assertMcNotShared($work, $test);
            }
            if (($changeMode || $adopt) && ! $this->mcStartEditable($test, $item['attempts'])) {
                throw ValidationException::withMessages(['mc_tests' => 'Die Startart und die Schlussfrist sind für diesen Test gesperrt.']);
            }
            if ($adopt && ($choice['start_mode'] !== 'individual' || ! $work->finalDeadlineInstant()?->isFuture())) {
                throw ValidationException::withMessages(['mc_tests' => 'Die Arbeit benötigt eine zukünftige endgültige Abgabefrist für diese Übernahme.']);
            }
            if ($test->status === 'draft' || $changeMode || $adopt) {
                $this->configureMcStart($work, $test, $item['attempts'], $choice['start_mode'], $releaseNow || $test->status === 'published', $adopt);
            }
            if (! $releaseNow) {
                continue;
            }
            if ($test->status === 'published') {
                continue;
            }
            $wasDraft = $test->status === 'draft';
            $published = app(McTestService::class)->publish($test, []);
            if ($wasDraft && $published->start_mode === 'individual' && ($deadline = $published->capDeadline($published->closes_at)) !== null) {
                FinalizeMultipleChoiceTest::dispatch((int) $published->id)->delay($deadline)->afterCommit();
            }
        }
    }

    /** @param list<string> $createdPaths */
    public function releaseSavedWithScheduledTest(TeachingCourseWork $work, bool $releaseNow, array &$createdPaths): void
    {
        abort_unless($releaseNow && ($this->hasPausedPreparation($work) || $this->hasPausedRelease($work)), 409,
            'Die gespeicherte Aufgabenfreigabe wurde inzwischen geändert.');
        abort_unless($this->hasLockedPreparation($this->mcTests($work, true)), 409,
            'Der MC-Teststand wurde inzwischen geändert. Bitte den Stand neu laden.');
        $release = $work->status['student_distribution'];
        $this->publish($work, $release['instructions'] ?? '', [], $createdPaths, $release['file_ids'] ?? [], releaseNow: true);
    }

    private function mcPreparationLocked(McTest $test): bool
    {
        return $test->commonPreparationLocked();
    }

    /** @param list<array{test: McTest}> $items */
    private function hasLockedPreparation(array $items): bool
    {
        return collect($items)->contains(fn (array $item): bool => $this->mcPreparationLocked($item['test']));
    }

    /** @param array<int, string>|null $expectedTests */
    public function pauseStudentRelease(TeachingCourseWork $work, int $actorId, ?array $expectedTests = null): void
    {
        $work->assertStudentReleaseOpen();
        $status = $work->status ?? [];
        $alreadyPaused = $this->hasPausedRelease($work);
        if (empty($status['student_distribution']['published_at']) || ($alreadyPaused && $expectedTests === null)) {
            return;
        }

        $testsToPause = [];
        foreach ($this->mcTests($work, true) as $item) {
            $test = $item['test'];
            if ($this->mcTestShared($work, $test)) {
                throw ValidationException::withMessages(['mc_tests' => 'Die Freigabe kann nicht pausiert werden, weil ein verknüpfter MC-Test auch zu einer anderen Arbeit gehört.']);
            }
            if ($test->status === 'published' && $test->paused_at === null && $this->mcHasOpenAttempts($item)) {
                abort_unless((int) $test->owner_id === $actorId, 403, 'Nur die zuständige Lehrkraft kann die Freigabe mit ihren MC-Tests gemeinsam pausieren.');
                if ($alreadyPaused) {
                    abort_unless(hash_equals($this->mcPauseVersion($test), $expectedTests[$test->id] ?? ''), 409,
                        'Der MC-Test wurde inzwischen geändert. Bitte den Stand neu laden und die Pause erneut bestätigen.');
                }
                $testsToPause[] = $test;
            }
        }
        $pausedAt = CarbonImmutable::now()->floorSecond();
        foreach ($testsToPause as $test) {
            app(McTestService::class)->pause($test, $actorId, $pausedAt);
        }

        if (! $alreadyPaused) {
            $status['student_distribution']['withdrawn_at'] = $pausedAt->toIso8601String();
            $work->update(['status' => $status]);
        }
    }

    /** End a published release while holding the work row lock. */
    public function endStudentRelease(TeachingCourseWork $work, int $actorId): void
    {
        if ($work->studentReleaseEnded()) {
            return;
        }
        $status = $work->status ?? [];
        $emailed = TeachingWorkSubmissions::confirmedEmailRecipients($work);
        abort_unless(! empty($status['student_distribution']['published_at']) || $emailed !== [], 409, 'Nur eine bereits bereitgestellte Arbeit kann beendet werden.');
        $sending = collect($status['assignment_mail']['batches'] ?? [])->flatMap(fn (array $batch): array => $batch['recipients'] ?? [])
            ->contains(fn (array $recipient): bool => in_array($recipient['state'] ?? null, ['queued', 'sending'], true));
        abort_if($sending, 409, 'Für diese Arbeit läuft ein Aufgabenversand. Beende die Freigabe, sobald der Versand abgeschlossen oder gestoppt ist.');

        $items = $this->mcTests($work, true, requireReady: false, endingActorId: $actorId);
        $endedAt = CarbonImmutable::now()->floorSecond();
        foreach ($items as $item) {
            $test = $item['test'];
            if (! isset($test->snapshot['work_stopped_at'])) {
                app(McTestService::class)->stopWholeTest($test, $item['attempts'], $endedAt);
            }
        }
        $status['student_distribution']['ended_at'] = $endedAt->toIso8601String();
        if ($emailed !== []) {
            $status['student_distribution']['ended_email_student_ids'] = $emailed;
        }
        $work->update(['status' => $status]);
        $this->signalDeadlineChangeAfterCommit($work);
    }

    /** @return list<array{test: McTest, attempts: Collection<int, McAttempt>, active_attempts: Collection<int, McAttempt>}> */
    private function mcTests(TeachingCourseWork $work, bool $lock = false, bool $requireReady = true, ?int $endingActorId = null): array
    {
        $bindings = $work->status['multiple_choice_tests'] ?? [];
        if (! is_array($bindings)) {
            throw ValidationException::withMessages(['mc_tests' => 'Die MC-Zuordnung ist unvollständig. Bitte die Arbeit prüfen.']);
        }
        if ($bindings === []) {
            return [];
        }
        if ($lock) {
            $work->teachingCourse->teachingCourseStudentsWithTrashed()->orderBy('id')->lockForUpdate()->get(['id']);
        }
        $expected = [];
        $inactive = [];
        foreach ($bindings as $slot => $binding) {
            if (! is_array($binding) || (int) ($binding['work_id'] ?? 0) !== (int) $work->id || ! is_numeric($binding['test_id'] ?? null)) {
                throw ValidationException::withMessages(['mc_tests' => 'Die MC-Zuordnung ist unvollständig. Bitte die Arbeit prüfen.']);
            }
            $testId = (int) $binding['test_id'];
            $group = $this->mcGroupAccounts($work, (string) $slot, $lock);
            foreach ($group['active'] as $studentId) {
                $expected[$testId][$studentId] = $studentId;
            }
            foreach ($group['inactive'] as $studentId) {
                $inactive[$testId][$studentId] = $studentId;
            }
            if (empty($expected[$testId]) && ($requireReady || empty($inactive[$testId]))) {
                throw ValidationException::withMessages(['mc_tests' => 'Dem verknüpften MC-Test sind für diese Arbeit keine aktiven Schüler:innen zugeordnet.']);
            }
        }
        $expected += array_fill_keys(array_keys($inactive), []);
        ksort($expected, SORT_NUMERIC);
        $items = [];
        foreach ($expected as $id => $studentIds) {
            $query = McTest::query()->where('school_id', $work->teachingCourse->school_id)
                ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where('owner_id', $work->teachingCourse->user_id);
            $test = ($lock ? $query->lockForUpdate() : $query)->find($id);
            if (! $test) {
                throw ValidationException::withMessages(['mc_tests' => 'Der verknüpfte MC-Test gehört nicht eindeutig zu dieser Arbeit.']);
            }
            if ($endingActorId !== null) {
                abort_if($this->mcTestShared($work, $test), 409,
                    'Die Freigabe kann nicht beendet werden, weil ein verknüpfter MC-Test auch zu einer anderen Arbeit gehört.');
                abort_unless((int) $test->owner_id === $endingActorId, 403, 'Nur die zuständige Lehrkraft kann die Freigabe mit ihren MC-Tests gemeinsam beenden.');
            }
            $attemptQuery = $test->attempts()->orderBy('id');
            $attempts = ($lock ? $attemptQuery->lockForUpdate() : $attemptQuery)->get();
            $required = array_values($studentIds);
            sort($required, SORT_NUMERIC);
            $activeAttempts = $attempts->filter(fn (McAttempt $attempt): bool => in_array((int) $attempt->student_id, $required, true))->values();
            $assigned = $activeAttempts->pluck('student_id')->map(fn (mixed $studentId): int => (int) $studentId)->sort()->values()->all();
            $inactiveIds = array_values($inactive[$id] ?? []);
            $unexpected = $attempts->filter(fn (McAttempt $attempt): bool => ! in_array((int) $attempt->student_id, $required, true)
                && ! in_array((int) $attempt->student_id, $inactiveIds, true));
            if ($assigned !== $required || $unexpected->isNotEmpty()) {
                throw ValidationException::withMessages(['mc_tests' => 'Die Teilnehmenden des MC-Tests stimmen nicht genau mit der zugeordneten Gruppe überein. Bitte die Zuordnung prüfen.']);
            }
            if ($requireReady) {
                $this->assertMcReady($test, $activeAttempts);
                if ($test->status !== 'published') {
                    $this->assertMcNotShared($work, $test);
                }
            }
            $items[] = ['test' => $test, 'attempts' => $attempts, 'active_attempts' => $activeAttempts];
        }

        return $items;
    }

    private function assertMcNotShared(TeachingCourseWork $work, McTest $test): void
    {
        if ($this->mcTestShared($work, $test)) {
            throw ValidationException::withMessages(['mc_tests' => 'Dieser MC-Test ist auch mit einer anderen Arbeit verknüpft. Bitte die Zuordnung vor der gemeinsamen Freigabe prüfen.']);
        }
    }

    public function mcTestShared(TeachingCourseWork $work, McTest $test): bool
    {
        return TeachingCourseWork::query()->whereKeyNot($work->id)->linkedToMultipleChoiceTest((int) $test->id)->exists();
    }

    /** @param array{test: McTest, attempts: Collection<int, McAttempt>} $item
     * @return array<string, mixed>
     */
    private function mcPreviewItem(TeachingCourseWork $work, array $item, int $actorId): array
    {
        $test = $item['test'];
        $editable = ! $work->studentReleaseEnded() && $this->mcStartEditable($test, $item['attempts']) && ! $this->mcTestShared($work, $test);
        $workDeadline = $work->finalDeadlineInstant();
        $individualDeadline = $this->individualMcDeadline($work, $test);
        $testDeadline = $test->start_mode === 'individual' ? $individualDeadline
            : ($test->paused_at === null ? $test->ends_at : null);
        $earlierDeadline = $testDeadline !== null && ($workDeadline === null || $testDeadline->lessThan($workDeadline))
            ? $testDeadline : null;
        $different = $workDeadline !== null && $individualDeadline !== null && ! $individualDeadline->equalTo($workDeadline);
        $openAttempts = $this->mcHasOpenAttempts($item);
        $canControlAttempts = $this->canControlWorkTestAttempts($work, $test, $actorId);

        return ['id' => $test->id, 'title' => $test->title, 'status' => $test->status, 'start_mode' => $test->start_mode,
            'duration_seconds' => $test->duration_seconds, 'closes_at' => $test->closes_at?->toIso8601String(),
            'individual_closes_at' => $individualDeadline?->toIso8601String(),
            'earlier_closes_at' => $earlierDeadline?->toIso8601String(),
            'work_deadline_conflict' => $test->hasWorkDeadlineConflict(),
            'starts_at' => $test->starts_at?->toIso8601String(), 'paused_at' => $test->paused_at?->toIso8601String(),
            'ends_at' => ($test->ends_at === null ? null : $test->capDeadline($test->ends_at)->toIso8601String()),
            'has_open_attempts' => $openAttempts,
            'preparation_locked' => $this->mcPreparationLocked($test),
            'management_action' => $this->mcManagementAction($work, $item, $actorId),
            'can_pause' => $canControlAttempts && $openAttempts && $test->paused_at === null,
            'can_control_attempts' => $canControlAttempts,
            'lifecycle_actions' => $this->mcLifecycleActions($work, $item, $actorId),
            'attempt_count' => $item['attempts']->count(), 'stopped_at' => $test->snapshot['work_stopped_at'] ?? null,
            'mode_editable' => $editable,
            'work_closes_at' => $workDeadline?->toIso8601String(),
            'deadline_differs' => $different,
            'can_adopt_final_deadline' => $editable && $different && $workDeadline->isFuture(),
            'version' => $this->mcVersion($item), 'pause_version' => $this->mcPauseVersion($test)];
    }

    private function canControlWorkTestAttempts(TeachingCourseWork $work, McTest $test, int $actorId): bool
    {
        $release = $work->status['student_distribution'] ?? [];

        return ! $work->studentReleaseEnded() && ! empty($release['published_at']) && empty($release['withdrawn_at']) && empty($release['deleted_at'])
            && $test->status === 'published' && (int) $test->owner_id === $actorId
            && empty($test->snapshot['work_stopped_at']) && ! $this->mcTestShared($work, $test);
    }

    /** @param array{test: McTest, attempts: Collection<int, McAttempt>, active_attempts: Collection<int, McAttempt>} $item */
    private function mcManagementAction(TeachingCourseWork $work, array $item, int $actorId): ?string
    {
        $test = $item['test'];
        $release = $work->status['student_distribution'] ?? [];
        $hasBeenReleased = ! empty($release['published_at']);
        if ($work->studentReleaseEnded() || (! $hasBeenReleased && ! $this->hasPausedPreparation($work)) || $test->status !== 'published'
            || (int) $test->owner_id !== $actorId || ! $this->mcHasOpenAttempts($item) || $this->mcTestShared($work, $test)) {
            return null;
        }
        if ($test->paused_at !== null) {
            return $hasBeenReleased || ! $this->mcStarted($test, $item['attempts']) ? 'resume' : null;
        }
        if (($test->start_mode === 'common' && ($test->starts_at !== null || $test->ends_at !== null)) || $this->hasPausedRelease($work)) {
            return 'pause';
        }

        return $hasBeenReleased && empty($release['withdrawn_at']) && $test->start_mode === 'common'
            && ! $this->mcStarted($test, $item['attempts']) ? 'countdown' : null;
    }

    /** @param array{test: McTest, attempts: Collection<int, McAttempt>, active_attempts: Collection<int, McAttempt>} $item
     * @return list<string>
     */
    private function mcLifecycleActions(TeachingCourseWork $work, array $item, int $actorId): array
    {
        $test = $item['test'];
        if ($work->studentReleaseEnded() || (int) $test->owner_id !== $actorId || $this->mcTestShared($work, $test)) {
            return [];
        }
        $actions = [];
        if (in_array($test->status, ['published', 'withdrawn'], true) && ($test->start_mode === 'common' || $test->closes_at?->isFuture())) {
            $actions[] = 'reset';
        }
        $started = $test->start_mode === 'common' ? $test->starts_at?->lessThanOrEqualTo($test->paused_at ?? now())
            : $item['attempts']->contains(fn (McAttempt $attempt): bool => $attempt->started_at !== null);
        $hasUnfinished = $test->paused_at === null ? $this->mcHasOpenAttempts($item)
            : $item['attempts']->contains(fn (McAttempt $attempt): bool => $attempt->completed_at === null && $attempt->submitted_at === null);
        if ($test->status === 'published' && $started && $hasUnfinished) {
            $actions[] = 'stop';
        }

        return [...$actions, 'delete'];
    }

    /** @param array{test: McTest, active_attempts: Collection<int, McAttempt>} $item */
    private function mcHasOpenAttempts(array $item): bool
    {
        $test = $item['test'];
        $clock = $test->paused_at ?? now();

        return $item['active_attempts']->contains(function (McAttempt $attempt) use ($test, $clock): bool {
            $deadline = $attempt->effectiveDeadline($test);
            if ($attempt->fixedWorkDeadline($test)?->isNowOrPast() || $test->hasWorkDeadlineConflict()) {
                return false;
            }

            $attemptClock = $attempt->individualPausedAt()?->min($clock) ?? $clock;

            return $attempt->completed_at === null && $attempt->submitted_at === null && ($deadline === null || $deadline->isAfter($attemptClock));
        });
    }

    /** @param array{test: McTest, attempts: Collection<int, McAttempt>} $item */
    private function mcVersion(array $item): string
    {
        return SchooltoolAssessmentJson::digest(['test' => $item['test']->getRawOriginal(),
            'attempts' => $item['attempts']->map(fn (McAttempt $attempt): array => $attempt->getRawOriginal())->all()]);
    }

    private function mcPauseVersion(McTest $test): string
    {
        return SchooltoolAssessmentJson::digest($test->getRawOriginal());
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
        return $test->starts_at !== null || $test->ends_at !== null || $attempts->contains(fn (McAttempt $attempt): bool => $attempt->started_at !== null
            || $attempt->submitted_at !== null || $attempt->completed_at !== null || ($attempt->answers ?? []) !== []);
    }

    /** @param Collection<int, McAttempt> $attempts */
    private function mcStartEditable(McTest $test, $attempts): bool
    {
        return ($test->status === 'draft' || ($test->status === 'published' && $test->paused_at !== null))
            && ! $test->commonPreparationLocked()
            && ! $this->mcStarted($test, $attempts)
            && ! $attempts->contains(fn (McAttempt $attempt): bool => $attempt->closes_at !== null
                || $attempt->reopened_until !== null || $attempt->pause_seconds > 0 || $attempt->control() !== []);
    }

    private function individualMcDeadline(TeachingCourseWork $work, McTest $test): ?CarbonImmutable
    {
        if ($test->start_mode === 'individual' && $test->closes_at !== null) {
            return CarbonImmutable::instance($test->closes_at);
        }
        if (is_string($test->snapshot['individual_deadline_override'] ?? null)) {
            return CarbonImmutable::parse($test->snapshot['individual_deadline_override']);
        }

        return $work->finalDeadlineInstant();
    }

    /** @param Collection<int, McAttempt> $attempts */
    private function configureMcStart(TeachingCourseWork $work, McTest $test, $attempts, string $mode, bool $requireDeadline, bool $adoptDeadline): void
    {
        $deadline = $mode === 'individual' ? ($adoptDeadline ? $work->finalDeadlineInstant() : $this->individualMcDeadline($work, $test)) : null;
        if ($requireDeadline && $mode === 'individual' && (! $deadline || ! $deadline->isFuture())) {
            throw ValidationException::withMessages(['mc_tests' => 'Für den individuellen MC-Test ist eine zukünftige Schlussfrist erforderlich. Bitte die Frist prüfen.']);
        }
        if ($this->mcStarted($test, $attempts) && ($test->start_mode !== $mode || ($mode === 'individual' && ! $test->closes_at?->equalTo($deadline)))) {
            throw ValidationException::withMessages(['mc_tests' => 'Dieser MC-Test wurde bereits begonnen. Seine Startart und Frist können hier nicht geändert werden.']);
        }
        if ($test->start_mode !== $mode || $adoptDeadline || ($mode === 'individual' && $test->closes_at != $deadline)) {
            $snapshot = $test->snapshot;
            if ($mode === 'common' && $test->closes_at && ($snapshot['work_deadline_inherited'] ?? null) !== true
                && (($snapshot['work_deadline_inherited'] ?? null) === false || ! $work->finalDeadlineInstant()?->equalTo($test->closes_at))) {
                $snapshot['individual_deadline_override'] = $test->closes_at->toIso8601String();
            }
            if ($adoptDeadline) {
                unset($snapshot['individual_deadline_override']);
            }
            $snapshot['start_mode'] = $mode;
            $snapshot['closes_at'] = $deadline?->toIso8601String();
            if ($mode === 'individual' && ($test->closes_at === null || $test->start_mode !== 'individual')) {
                $snapshot['work_deadline_inherited'] = ! isset($snapshot['individual_deadline_override']);
            }
            if ($adoptDeadline) {
                $snapshot['work_deadline_inherited'] = true;
            }
            if ($test->status === 'published') {
                McTest::query()->whereKey($test->id)->update(['start_mode' => $mode, 'closes_at' => $deadline,
                    'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
            } else {
                $test->forceFill(['start_mode' => $mode, 'closes_at' => $deadline, 'snapshot' => $snapshot])->save();
            }
        }
    }

    public function version(TeachingCourseWork $work): string
    {
        $attributes = $work->getRawOriginal();
        foreach (['groups', 'status'] as $field) {
            $attributes[$field] = $work->getAttribute($field);
        }
        // Student progress cannot invalidate a teacher's open release or pause dialog.
        unset($attributes['updated_at'], $attributes['status']['portal_submissions'],
            $attributes['status']['submission_started_at'], $attributes['status']['portal_started_student_ids']);

        return SchooltoolAssessmentJson::digest($attributes);
    }

    /** @return list<int> */
    public function recipients(TeachingCourseWork $work, bool $lock = false, ?array $groups = null): array
    {
        $assigned = collect($groups ?? $work->groups ?? [])->flatMap(fn (array $group): array => $group['student_ids'] ?? [])->map(fn (mixed $id): int => (int) $id)->unique();

        $query = $work->teachingCourse->teachingCourseStudents()->whereNull('canceled_at')->whereIn('user_id', $assigned)
            ->with('user:id,is_active');
        $memberships = ($lock ? $query->lockForUpdate() : $query)->get(['id', 'user_id']);
        $directIds = $memberships->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $ids = $memberships->filter(fn ($membership): bool => $membership->user
            && ($membership->user->is_active === null || (bool) $membership->user->is_active))
            ->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $importsQuery = $work->teachingCourse->teachingCourseStudents()->whereNull('canceled_at')->whereNull('user_id')
            ->whereIn('import116_id', $assigned)->with('import116.user.roles');
        $seenImports = [];
        foreach (($lock ? $importsQuery->lockForUpdate() : $importsQuery)->get() as $membership) {
            $sourceId = (int) $membership->import116_id;
            if (in_array($sourceId, $directIds, true) || in_array($sourceId, $seenImports, true)) {
                throw ValidationException::withMessages(['students' => 'Eine Gruppenkennung ist nicht eindeutig einem Schülerkonto zugeordnet.']);
            }
            $seenImports[] = $sourceId;
            $import = $membership->import116;
            $user = $import?->user;
            if (! $user || (int) $import->school_id !== (int) $work->teachingCourse->school_id
                || (int) $import->schoolyear_id !== (int) $work->teachingCourse->schoolyear_id
                || (int) $user->school_id !== (int) $work->teachingCourse->school_id) {
                throw ValidationException::withMessages(['students' => 'Eine Gruppenkennung ist nicht eindeutig einem Schülerkonto zugeordnet.']);
            }
            if ($user->is_active === null || (bool) $user->is_active) {
                if (! $user->hasRole('student', 'web')) {
                    throw ValidationException::withMessages(['students' => 'Für eine Person der Prüfungsgruppe fehlt die Schülerrolle.']);
                }
                $ids[] = (int) $user->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int> */
    public function verifiedMcGroupAccounts(TeachingCourseWork $work, string $slot, bool $lock = false): array
    {
        $active = $this->mcGroupAccounts($work, $slot, $lock)['active'];
        if ($active === []) {
            throw ValidationException::withMessages(['mc_selection' => 'Für diesen Termin ist keine aktive Prüfungsgruppe mit Schülerkonten zugeordnet.']);
        }

        return $active;
    }

    /** @return list<int> */
    public function verifiedInactiveMcGroupAccounts(TeachingCourseWork $work, string $slot, bool $lock = false): array
    {
        return $this->mcGroupAccounts($work, $slot, $lock)['inactive'];
    }

    /** @return array{active: list<int>, inactive: list<int>} */
    private function mcGroupAccounts(TeachingCourseWork $work, string $slot, bool $lock = false): array
    {
        $course = $work->teachingCourse;
        $groupIds = collect($work->groups ?? [])->filter(fn (array $group): bool => $slot === 'all'
            || ($group['date'] ?? $work->date_for_all_groups?->format('Y-m-d')) === $slot)
            ->flatMap(fn (array $group): array => $group['student_ids'] ?? [])->unique()->values()->all();
        $membershipQuery = $course->teachingCourseStudentsWithTrashed()->with(['user.roles', 'import116.user.roles'])->orderBy('id');
        $memberships = ($lock ? $membershipQuery->lockForUpdate() : $membershipQuery)->get();
        $ids = [];
        $inactive = [];
        foreach ($groupIds as $groupId) {
            if (! is_numeric($groupId) || (int) $groupId <= 0) {
                throw ValidationException::withMessages(['mc_selection' => 'Eine Person der Prüfungsgruppe ist nicht eindeutig zugeordnet.']);
            }
            $matches = $memberships->filter(fn ($membership): bool => (int) $membership->user_id === (int) $groupId
                || ($membership->user_id === null && (int) $membership->import116_id === (int) $groupId));
            if ($matches->count() !== 1) {
                throw ValidationException::withMessages(['mc_selection' => 'Eine Person der Prüfungsgruppe ist nicht eindeutig der Kursliste zugeordnet.']);
            }
            $membership = $matches->first();
            if ($membership->canceled_at !== null || $membership->deleted_at !== null) {
                continue;
            }
            $import = $membership->import116;
            $user = $membership->user ?? $import?->user;
            if (! $user || (int) $user->school_id !== (int) $course->school_id) {
                throw ValidationException::withMessages(['mc_selection' => 'Für eine Person der Prüfungsgruppe fehlt ein aktives, zur Schule gehörendes Schülerkonto.']);
            }
            $verifiedImport = $import && (int) $import->school_id === (int) $course->school_id
                && (int) $import->schoolyear_id === (int) $course->schoolyear_id
                && (int) $import->user_id === (int) $user->id;
            if ($import && ! $verifiedImport) {
                throw ValidationException::withMessages(['mc_selection' => 'Der Schülerlistenbeleg einer Person widerspricht der Kurs- oder Kontozuordnung.']);
            }
            if ($user->is_active !== null && ! (bool) $user->is_active) {
                $inactive[] = (int) $user->id;

                continue;
            }
            if (! $user->hasRole('student', 'web')) {
                throw ValidationException::withMessages(['mc_selection' => 'Für '.trim($user->first_name.' '.$user->last_name).' fehlt die Schülerrolle des zugeordneten Kontos. Bitte die Kontoberechtigung prüfen.']);
            }
            if ((int) $user->schoolyear_id !== (int) $course->schoolyear_id && ! $verifiedImport) {
                throw ValidationException::withMessages(['mc_selection' => 'Das Schülerkonto einer Person ist für dieses Kurs-Schuljahr nicht eindeutig belegt.']);
            }
            $ids[] = (int) $user->id;
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        $inactive = array_values(array_unique($inactive));
        sort($inactive, SORT_NUMERIC);

        return ['active' => $ids, 'inactive' => $inactive];
    }

    public function visibleTo(TeachingCourseWork $work, User $student): bool
    {
        $release = $work->status['student_distribution'] ?? [];

        return ! empty($release['published_at']) && empty($release['withdrawn_at'])
            && $this->assignedTo($work, $student);
    }

    public function hasPausedPreparation(TeachingCourseWork $work): bool
    {
        $release = $work->status['student_distribution'] ?? [];

        return ! empty($release['prepared_at']) && empty($release['published_at']) && empty($release['withdrawn_at']);
    }

    private function hasPausedRelease(TeachingCourseWork $work): bool
    {
        $release = $work->status['student_distribution'] ?? [];

        return ! $work->studentReleaseEnded() && ! empty($release['published_at']) && ! empty($release['withdrawn_at']);
    }

    public function taskContentsLocked(TeachingCourseWork $work): bool
    {
        $release = $work->status['student_distribution'] ?? [];

        return ! empty($release['withdrawn_at'])
            || (empty($release['published_at']) && (! empty($release['prepared_at']) || ! empty($release['deleted_at'])));
    }

    public function deletePreparation(TeachingCourseWork $work): void
    {
        $work->assertStudentReleaseOpen();
        abort_unless($this->hasPausedPreparation($work) || $this->hasPausedRelease($work), 409,
            'Nur eine vorbereitete oder pausierte Verteilung kann zurückgenommen werden.');
        abort_if($this->hasLockedPreparation($this->mcTests($work, true)), 409,
            'Die Vorbereitung ist gesperrt, weil der gemeinsame MC-Countdown bereits angesetzt wurde.');
        $status = $work->status;
        // Known personal MC links must stay locked until a new release.
        $status['student_distribution'] = ['deleted_at' => now()->toIso8601String()];
        $work->update(['status' => $status]);
    }

    /** @return array<string, mixed>|null */
    public function taskForStudent(TeachingCourseWork $work, User $student): ?array
    {
        $status = $this->studentTaskStatus($work, $student);
        if ($status === null) {
            return null;
        }
        $submission = $this->studentSubmissionDetails($work, $student, $status);
        if ($status === 'released' || ($status === 'ended' && $this->visibleTo($work, $student))) {
            return $this->forStudent($work, $student) + $submission + [
                'can_start_task' => $status === 'released' && (int) auth()->id() === (int) $student->id && ! TeachingWorkSubmissions::expired($work),
                'task_started' => in_array((int) $student->id, $work->status['portal_started_student_ids'] ?? [], true),
            ]
                + ($status === 'ended' ? ['status' => 'ended'] : []);
        }

        $dates = $this->studentGroups($work, $student)
            ->map(fn (array $group): ?string => $group['date'] ?? $work->date_for_all_groups?->format('Y-m-d'))
            ->filter()->unique()->sort()->values()->all();

        return ['id' => $work->id, 'title' => $work->title, 'status' => $status] + $submission
            + $this->studentTaskDetails($work, $dates, $this->studentMcBindings($work, $dates));
    }

    /** @return array<string, mixed>|null */
    public function deadlineSummaryForStudent(TeachingCourseWork $work, User $student): ?array
    {
        $status = $this->studentTaskStatus($work, $student);

        if ($status === null) {
            return null;
        }

        $method = TeachingWorkSubmissions::method($work);
        $personal = app(TeachingWorkPortalReopening::class)->deadlineFor($work, $student, $status);

        return ['id' => $work->id, 'status' => $status] + $this->studentTaskDeadlines($work)
            + ($personal ? ['submission_deadline_at' => $personal->toIso8601String()] : [])
            + ($method === null ? [] : ['submission_method' => $method]);
    }

    public function studentTaskStatus(TeachingCourseWork $work, User $student, bool $knownRecipient = false): ?string
    {
        $release = $work->status['student_distribution'] ?? [];
        $status = match (true) {
            $work->studentReleaseEnded() => 'ended',
            $this->hasPausedRelease($work) => 'paused',
            ! empty($release['published_at']) => 'released',
            $this->hasPausedPreparation($work) => 'not_released',
            default => null,
        };

        $assigned = $status !== null && $this->assignedTo($work, $student, $knownRecipient);
        if ($status === 'ended') {
            return $assigned || in_array((int) $student->id, $release['ended_email_student_ids'] ?? [], true)
                ? $status : null;
        }
        if ($assigned && $status !== 'not_released') {
            return $status;
        }

        if (TeachingWorkSubmissions::acceptedMail($work, $student)
            && ($assigned || $knownRecipient || in_array((int) $student->id, $this->recipients($work), true))) {
            return 'email_delivery';
        }

        return $assigned ? $status : null;
    }

    /** @return array<string,mixed> */
    private function studentSubmissionDetails(TeachingCourseWork $work, User $student, string $status): array
    {
        $method = TeachingWorkSubmissions::method($work);
        $files = TeachingWorkSubmissions::receipts($work, $student);
        if ($method === null && $files === []) {
            return [];
        }

        $personal = app(TeachingWorkPortalReopening::class)->deadlineFor($work, $student, $status);

        return ['submission_method' => $method, 'submission_files' => $files,
            'can_submit' => (int) auth()->id() === (int) $student->id && ($method['channel'] ?? null) === 'portal'
                && ($personal ? ! $personal->isPast()
                    : in_array($status, ['released', 'email_delivery'], true) && ! TeachingWorkSubmissions::expired($work))]
            + ($personal ? ['submission_deadline_at' => $personal->toIso8601String()] : []);
    }

    /**
     * @param  list<string>  $dates
     * @param  \Illuminate\Support\Collection<array-key, array>  $bindings
     * @return array<string, mixed>
     */
    private function studentTaskDetails(TeachingCourseWork $work, array $dates, \Illuminate\Support\Collection $bindings): array
    {
        $release = $work->status['student_distribution'] ?? [];
        $details = ['dates' => $dates] + $this->studentTaskDeadlines($work);
        if (($gradingPoints = $this->gradingPoints($work)) !== null) {
            $details['grading_points'] = $gradingPoints;
        }

        $preparation = count($dates) === 1 ? app(DropboxModuleLinks::class)->cachedAssessmentPreparation($work, $dates[0]) : null;
        if (! empty($release['file_ids']) || ($preparation['components']['aufgabenblatt']['included'] ?? false)) {
            $details['assignment_kind'] = 'worksheet';
        } elseif (filled($release['instructions'] ?? null)) {
            $details['assignment_kind'] = 'instructions';
        }
        $folderMc = $preparation['components']['multiple_choice'] ?? [];
        $hasMultipleChoice = $bindings->isNotEmpty() || ($folderMc['included'] ?? false);
        if ($hasMultipleChoice) {
            $details['has_multiple_choice'] = true;
            $mcPoints = $folderMc['points'] ?? $this->studentMcPoints($work, $bindings);
            if (is_numeric($mcPoints)) {
                $details['mc_points'] = (float) $mcPoints;
            }
        }
        if ($preparation !== null) {
            $details['has_assessment'] = true;
            $details['assignment_points'] = $preparation['components']['aufgabenblatt']['points'] ?? null;
            if (is_numeric($details['assignment_points']) && (! $hasMultipleChoice || isset($details['mc_points']))) {
                $details['assessment_points'] = (float) $details['assignment_points'] + ($details['mc_points'] ?? 0);
            }
        }

        return $details;
    }

    /** @return array<string, array{date: string, time: ?string}> */
    private function studentTaskDeadlines(TeachingCourseWork $work): array
    {
        return $work->finish_until_date === null ? [] : ['deadline' => $work->finalDeadline()];
    }

    /** @param  \Illuminate\Support\Collection<array-key, array>  $bindings */
    private function studentMcPoints(TeachingCourseWork $work, \Illuminate\Support\Collection $bindings): ?float
    {
        $testIds = $bindings->pluck('test_id')->map(fn (mixed $id): int => (int) $id)->unique()->values();
        if ($testIds->count() !== 1) {
            return null;
        }

        $course = $work->teachingCourse;
        $points = McTest::query()->whereKey($testIds->first())
            ->where('school_id', $course->school_id)->where('schoolyear_id', $course->schoolyear_id)
            ->where('owner_id', $course->user_id)->value('total_points');

        return is_numeric($points) ? (float) $points : null;
    }

    private function gradingPoints(TeachingCourseWork $work): ?float
    {
        $course = $work->teachingCourse;
        if (! $course->user || ! $work->type) {
            return null;
        }
        $definition = app(TeachingCourseStudentEntryService::class)->entryDefinitionsForCourse($course->user, $course)
            ->firstWhere('short_name', $work->type);
        $schema = $definition === null && $course->teaching_schema_id
            ? app(TeachingService::class)->schemaById($course->user, $course->teaching_schema_id, $course->schoolyear_id) : null;
        $type = $definition?->only(['category', 'properties_mode', 'maximum_points'])
            ?? collect($schema['works'] ?? [])->firstWhere('short_name', $work->type);
        $maximum = $type['maximum_points'] ?? null;

        return ($type['category'] ?? 'Benotung') === 'Benotung' && ($type['properties_mode'] ?? null) === 'points'
            && is_numeric($maximum) && is_finite((float) $maximum) && $maximum > 0 ? (float) $maximum : null;
    }

    private function assignedTo(TeachingCourseWork $work, User $student, bool $knownRecipient = false): bool
    {
        return in_array((int) $student->id, $work->status['student_distribution']['student_ids'] ?? [], true)
            && ($knownRecipient || in_array((int) $student->id, $this->recipients($work), true));
    }

    public function mcAccessAllowed(McAttempt $attempt): bool
    {
        return $this->mcAccessBlockStatus($attempt) === null;
    }

    public function mcAccessBlockStatus(McAttempt $attempt): ?int
    {
        $works = TeachingCourseWork::query()->linkedToMultipleChoiceTest((int) $attempt->test_id)
            ->whereHas('teachingCourse', fn ($query) => $query->where('school_id', $attempt->test->school_id)
                ->where('schoolyear_id', $attempt->test->schoolyear_id)->where('user_id', $attempt->test->owner_id));
        if ($attempt->completed_at === null && $attempt->test->workReleaseEnded()) {
            return 410;
        }
        $student = $attempt->student;
        $hasMailAccess = fn (TeachingCourseWork $work): bool => $student !== null && TeachingWorkSubmissions::acceptedMail($work, $student, (int) $attempt->test_id)
            && in_array((int) $student->id, $this->recipients($work), true);
        if ((clone $works)->whereNotNull('status->student_distribution->deleted_at')->get()->contains(fn ($work) => ! $hasMailAccess($work))) {
            return 404;
        }
        if ($attempt->started_at !== null || $attempt->completed_at !== null || ($attempt->answers ?? []) !== []) {
            return null;
        }

        return $works->whereNotNull('status->student_distribution->prepared_at')->whereNull('status->student_distribution->published_at')
            ->get()->contains(fn ($work) => ! $hasMailAccess($work)) ? 423 : null;
    }

    public function isVerifiedWorkTestForStudent(McAttempt $attempt, User $student): bool
    {
        $test = $attempt->test;
        if (! $test || (int) $attempt->student_id !== (int) $student->id || (int) $test->school_id !== (int) $student->school_id) {
            return false;
        }
        $works = TeachingCourseWork::query()->with('teachingCourse')
            ->whereHas('teachingCourse', fn ($query) => $query->where('school_id', $test->school_id)
                ->where('schoolyear_id', $test->schoolyear_id)->where('user_id', $test->owner_id))
            ->where(function ($query) use ($test): void {
                $query->whereRaw('JSON_CONTAINS(JSON_EXTRACT(status, ?), ?)', ['$.multiple_choice_tests.*.test_id', json_encode((int) $test->id, JSON_THROW_ON_ERROR)])
                    ->orWhereRaw('JSON_CONTAINS(JSON_EXTRACT(status, ?), ?)', ['$.multiple_choice_tests.*.test_id', json_encode((string) $test->id, JSON_THROW_ON_ERROR)]);
            })->get();
        foreach ($works as $work) {
            foreach ($work->status['multiple_choice_tests'] ?? [] as $slot => $binding) {
                if (! is_array($binding) || (int) ($binding['work_id'] ?? 0) !== (int) $work->id
                    || (int) ($binding['test_id'] ?? 0) !== (int) $test->id) {
                    continue;
                }
                try {
                    $sourceIds = $work->teachingCourse->teachingCourseStudentsWithTrashed()->whereNull('user_id')
                        ->whereHas('import116', fn ($query) => $query->where('user_id', $student->id))->pluck('import116_id')
                        ->map(fn ($id): int => (int) $id)->all();
                    $sourceIds[] = (int) $student->id;
                    $personalWork = clone $work;
                    $personalWork->groups = array_map(function (array $group) use ($sourceIds): array {
                        $group['student_ids'] = array_values(array_filter($group['student_ids'] ?? [],
                            fn ($id): bool => in_array((int) $id, $sourceIds, true)));

                        return $group;
                    }, $work->groups ?? []);
                    if ($this->verifiedMcGroupAccounts($personalWork, (string) $slot) === [(int) $student->id]) {
                        return true;
                    }
                } catch (ValidationException) {
                    continue;
                }
            }
        }

        return false;
    }

    /** @param list<UploadedFile> $uploads
     * @param  list<string>  $createdPaths
     * @param  list<string>  $retainedFileIds
     */
    public function publish(TeachingCourseWork $work, string $instructions, array $uploads, array &$createdPaths, array $retainedFileIds = [], bool $releaseNow = false, bool $retainPausedRelease = false): void
    {
        $work->assertStudentReleaseOpen();
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
        $release = $work->status['student_distribution'] ?? [];
        $keepPause = ! $releaseNow && $retainPausedRelease && $this->hasPausedRelease($work);
        $work->status = array_merge($work->status ?? [], ['student_task_files' => $files, 'student_distribution' => [
            'prepared_at' => $release['prepared_at'] ?? now()->toIso8601String(),
            'published_at' => $releaseNow ? now()->toIso8601String() : ($keepPause ? $release['published_at'] : null),
            'withdrawn_at' => $keepPause ? $release['withdrawn_at'] : null, 'instructions' => trim($instructions), 'student_ids' => $recipients,
            'file_ids' => array_column($activeFiles, 'id'),
        ]]);
        $work->save();
    }

    /** @return array<string, mixed> */
    public function forStudent(TeachingCourseWork $work, User $student): array
    {
        $groups = $this->studentGroups($work, $student);
        $group = $groups->first();
        $date = $group['date'] ?? $work->date_for_all_groups?->format('Y-m-d');
        $dates = $groups->map(fn (array $group): ?string => $group['date'] ?? $work->date_for_all_groups?->format('Y-m-d'))->filter()->unique()->values()->all();
        $bindings = $this->studentMcBindings($work, $dates);
        $tests = $bindings->isEmpty() || (int) auth()->id() !== (int) $student->id ? collect() : McTest::query()
            ->whereIn('id', $bindings->pluck('test_id'))->where('school_id', $work->teachingCourse->school_id)
            ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where('owner_id', $work->teachingCourse->user_id)
            ->where('status', 'published')->whereNull('access_links_disabled_at')
            ->with(['attempts' => fn ($query) => $query->where('student_id', $student->id)])->get();

        return ['id' => $work->id, 'title' => $work->title, 'instructions' => $work->status['student_distribution']['instructions'],
            'published_at' => $work->status['student_distribution']['published_at'], 'date' => $date,
            'files' => collect($work->status['student_task_files'] ?? [])->filter(fn (array $file): bool => in_array($file['id'], $work->status['student_distribution']['file_ids'] ?? [], true))->map(fn (array $file): array => ['id' => $file['id'], 'name' => $file['name'],
                'url' => route('student.course-work.task-file', ['courseId' => $work->teaching_course_id, 'course_work' => $work->id, 'fileId' => $file['id']], false)])->values()->all(),
            'tests' => $tests->filter(fn (McTest $test): bool => filled($test->attempts->first()?->access_token)
                && (! $work->studentReleaseEnded() || $test->attempts->first()->completed_at !== null))->map(fn (McTest $test): array => [
                    'title' => $test->title, 'url' => route('multiple-choice.entry', ['course' => $work->teaching_course_id], false).'#access='.rawurlencode($test->attempts->first()->access_token),
                    'attempt_state' => $this->studentMcAttemptState($test, $test->attempts->first()),
                ])->values()->all()] + $this->studentTaskDetails($work, $dates, $bindings);
    }

    /** @return \Illuminate\Support\Collection<array-key, array> */
    private function studentMcBindings(TeachingCourseWork $work, array $dates): \Illuminate\Support\Collection
    {
        return collect($work->status['multiple_choice_tests'] ?? [])->filter(fn (mixed $binding, mixed $slot): bool => is_array($binding)
            && ! empty($binding['test_id']) && ($binding['work_id'] ?? null) === $work->id
            && ($slot === 'all' || in_array($slot, $dates, true)));
    }

    /** @return \Illuminate\Support\Collection<int, array> */
    private function studentGroups(TeachingCourseWork $work, User $student): \Illuminate\Support\Collection
    {
        return collect($work->groups ?? [])->filter(fn (array $group): bool => in_array((int) $student->id, $this->recipients($work, false, [$group]), true));
    }

    /** @return list<string|null> */
    public function submissionDatesForStudent(TeachingCourseWork $work, User $student): array
    {
        return $this->studentGroups($work, $student)
            ->map(fn (array $group): ?string => $group['date'] ?? $work->date_for_all_groups?->format('Y-m-d'))
            ->unique()->values()->all();
    }

    private function studentMcAttemptState(McTest $test, McAttempt $attempt): string
    {
        $deadline = $attempt->effectiveDeadline($test);
        $clock = $attempt->individualPausedAt()?->min($test->paused_at ?? now()) ?? $test->paused_at ?? now();

        if ($attempt->completed_at !== null) {
            return 'finished';
        }
        if ($test->hasWorkDeadlineConflict()) {
            return 'unavailable';
        }
        if ($attempt->fixedWorkDeadline($test)?->isNowOrPast() || ($deadline !== null && $deadline->lessThanOrEqualTo($clock))) {
            return 'finished';
        }

        if ($test->paused_at !== null || $attempt->individualPausedAt() !== null) {
            return 'paused';
        }

        if ($attempt->started_at === null) {
            return 'not_started';
        }

        return $deadline === null ? 'unavailable' : 'in_progress';
    }
}
