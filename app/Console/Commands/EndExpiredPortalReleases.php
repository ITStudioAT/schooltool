<?php

namespace App\Console\Commands;

use App\Models\TeachingCourseWork;
use App\Services\TeachingWorkStudentDistribution;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[Signature('teaching:end-expired-portal-releases')]
#[Description('End published work releases after the last permitted portal submission or MC window')]
class EndExpiredPortalReleases extends Command
{
    public function handle(TeachingWorkStudentDistribution $distribution): int
    {
        $count = 0;
        $today = now('Europe/Vienna')->toDateString();
        $works = TeachingCourseWork::query()->where('finish_until_date', '<=', $today)
            ->where('status->submission_method->channel', 'portal')->whereNull('status->student_distribution->ended_at')
            ->whereNull('status->student_distribution->deleted_at')->whereNull('status->deletion_receipt')
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $published) => $published->whereNotNull('status->student_distribution->published_at')
                    ->whereNull('status->student_distribution->withdrawn_at'))->orWhereNotNull('status->assignment_mail->batches');
            })->select('id')->lazyById(100);
        foreach ($works as $candidate) {
            try {
                $ended = DB::transaction(function () use ($candidate, $distribution): bool {
                    $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->find($candidate->id);

                    return $work?->teachingCourse && $distribution->endStudentRelease($work, (int) $work->teachingCourse->user_id, afterPortalDeadline: true);
                }, 3);
                $count += (int) $ended;
            } catch (ValidationException) {
                // An ambiguous MC assignment must be resolved before ending this release.
            } catch (HttpExceptionInterface $exception) {
                if (! in_array($exception->getStatusCode(), [403, 409], true)) {
                    throw $exception;
                }
            }
        }
        $this->info("{$count} Portal-Freigaben beendet.");

        return self::SUCCESS;
    }
}
