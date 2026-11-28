<?php

namespace App\Services;

use App\Exceptions\DropboxExerciseRequestFailed;
use App\Jobs\PublishTeachingWorkActions;
use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\TeachingWorkModuleLink;
use App\Support\TeachingWorkActionHistory;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class TeachingWorkActionLog
{
    private const Table = 'teaching_work_action_events';

    public function __construct(
        private DropboxWorkImport $dropbox,
        private DropboxModuleLinks $modules,
        private TeachingAssessmentTargetGuard $targets,
        private TeachingDirectAssessment $assessment,
    ) {}

    public function record(TeachingCourseWork $work, string $action, string $result, string $actor, ?string $date): void
    {
        if (! Schema::hasTable(self::Table)) {
            return;
        }

        $id = Str::uuid7()->toString();
        $event = [
            'id' => $id,
            'at' => now('UTC')->format('Y-m-d\TH:i:s.u\Z'),
            'action' => $action,
            'actor' => $actor,
            'source' => app()->isProduction() ? 'online' : 'local',
            'result' => $result,
        ];
        $this->events()->insert([
            'id' => $id,
            'work_id' => $work->id,
            'course_id' => $work->teaching_course_id,
            'scheduled_date' => $date,
            'event' => json_encode($event, JSON_THROW_ON_ERROR),
            'available_at' => now(),
        ]);
    }

    public function queuePending(): void
    {
        if (! $this->dropbox->configured() || ! Schema::hasTable(self::Table)) {
            return;
        }

        $works = $this->events()->whereNull('published_at')->where('available_at', '<=', now())
            ->groupBy('work_id')->orderByRaw('MIN(available_at)')->limit(30)->pluck('work_id');
        foreach ($works as $workId) {
            PublishTeachingWorkActions::dispatch((int) $workId)->onQueue('maintenance');
        }
    }

    public function publish(int $workId): void
    {
        $pending = $this->events()->where('work_id', $workId)->whereNull('published_at')->whereNotNull('available_at')
            ->orderBy('available_at')->orderBy('id')->limit(250)->get();
        if ($pending->isEmpty()) {
            return;
        }

        $work = TeachingCourseWork::query()->with('teachingCourse')->find($workId);
        if (! $work?->teachingCourse) {
            // The journal survives a deleted work even if its status file was also deleted.
            $this->events()->whereIn('id', $pending->pluck('id'))->update(['available_at' => null]);

            return;
        }

        $wrongCourse = $pending->reject(fn ($row): bool => (int) $row->course_id === $work->teaching_course_id);
        $this->events()->whereIn('id', $wrongCourse->pluck('id'))->update(['available_at' => null]);
        $pending = $pending->diffKeys($wrongCourse);
        if ($pending->isEmpty()) {
            return;
        }

        // Keep events until there is a valid Dropbox destination. A new action makes the
        // work eligible immediately and includes any older pending events in the next batch.
        $this->events()->whereIn('id', $pending->pluck('id'))->update(['available_at' => now()->addMinutes(5)]);
        $link = $this->binding($work);
        $connection = $link ? DropboxConnection::query()->find($link->dropbox_connection_id) : null;
        if (! $connection || $connection->revoked_at || $connection->user_id !== $work->teachingCourse->user_id
            || ! $this->dropbox->canWrite($connection) || isset($work->status['deletion_receipt'])) {
            $this->events()->whereIn('id', $pending->pluck('id'))->update(['available_at' => now()->addHours(6)]);

            return;
        }

        $dates = $this->targets->dates($work);
        $global = $pending->whereNull('scheduled_date');
        $confirmed = [];
        foreach ($dates as $date) {
            $dated = $pending->where('scheduled_date', $date);
            $selected = $global->concat($dated);
            if ($selected->isEmpty()) {
                continue;
            }
            try {
                if ($this->append($connection, $work, $link, $date, $selected)) {
                    $confirmed[] = $date;
                    $this->markPublished($dated);
                }
            } catch (Throwable $exception) {
                logger()->warning('Teaching work action history is pending.', [
                    'work_id' => $workId,
                    'exception_class' => $exception::class,
                ]);
            }
        }
        if ($dates !== [] && $confirmed === $dates) {
            $this->markPublished($global);
        }
    }

    private function binding(TeachingCourseWork $work): TeachingWorkModuleLink|TeachingWorkDropboxFolder|null
    {
        if (! $this->dropbox->installed()) {
            return null;
        }
        $module = $this->modules->current($work);
        if ($module) {
            return $module;
        }

        $folders = TeachingWorkDropboxFolder::query()->where('teaching_course_work_id', $work->id)->limit(2)->get();

        return $folders->count() === 1 ? $folders->first() : null;
    }

    private function append(DropboxConnection $connection, TeachingCourseWork $work,
        TeachingWorkModuleLink|TeachingWorkDropboxFolder $link, string $date, Collection $events): bool
    {
        if ($link instanceof TeachingWorkDropboxFolder) {
            return $this->appendStatus($connection, $work, $link, $date, $events);
        }

        $published = false;
        $this->assessment->withModuleLock($link->folder_id, function (Closure $renewLock) use ($connection, $work, $link, $date, $events, &$published): void {
            $published = $this->appendStatus($connection, $work, $link, $date, $events, $renewLock);
        });

        return $published;
    }

    private function appendStatus(DropboxConnection $connection, TeachingCourseWork $work,
        TeachingWorkModuleLink|TeachingWorkDropboxFolder $link, string $date, Collection $events, ?Closure $renewLock = null): bool
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $verified = $this->modules->verifiedExerciseStatus($connection, $work, $link, $date);
            // Preparation plans use the current status revision as a proof.
            if (! $verified['record']['prepared']) {
                return false;
            }

            ['folder' => $folder, 'document' => $document] = $verified;
            $record = json_decode($document['contents'], true, 32, JSON_THROW_ON_ERROR);
            $history = $record[TeachingWorkActionHistory::KEY] ?? [];
            $known = array_fill_keys(array_column($history, 'id'), true);
            $additions = $events->reject(fn ($row): bool => isset($known[$row->id]));
            if ($additions->isEmpty()) {
                return true;
            }
            foreach ($additions as $row) {
                $history[] = json_decode($row->event, true, 16, JSON_THROW_ON_ERROR);
            }
            usort($history, static fn (array $left, array $right): int => [$left['at'], $left['id']] <=> [$right['at'], $right['id']]);
            $record[TeachingWorkActionHistory::KEY] = $history;
            $contents = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

            $current = $work->fresh();
            $currentConnection = $connection->fresh();
            if (! $current || isset($current->status['deletion_receipt'])
                || $current->teaching_course_id !== $work->teaching_course_id
                || $this->binding($current)?->getAttributes() !== $link->getAttributes()
                || ! $currentConnection || $currentConnection->revoked_at
                || $currentConnection->account_id !== $connection->account_id
                || ! $this->dropbox->canWrite($currentConnection)) {
                return false;
            }

            try {
                $renewLock?->__invoke();
                $this->dropbox->writeExerciseDocument($connection, $folder['id'], 'status.json', $contents, $document['rev'], $folder['path_lower']);

                return true;
            } catch (DropboxExerciseRequestFailed $exception) {
                if ($exception->diagnostic()['category'] !== 'conflict' || $attempt === 2) {
                    throw $exception;
                }
                // Reread and merge any event written by the other installation.
            }
        }

        return false;
    }

    private function markPublished(Collection $events): void
    {
        if ($events->isNotEmpty()) {
            $this->events()->whereIn('id', $events->pluck('id'))->update(['published_at' => now(), 'available_at' => null]);
        }
    }

    private function events(): Builder
    {
        return DB::table(self::Table);
    }
}
