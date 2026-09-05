<?php

namespace App\Console\Commands;

use App\Jobs\Teaching\Import116Job;
use App\Mail\TeachingReminderMail;
use App\Services\TeachingReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('teaching:send-reminders')]
#[Description('Send due teaching reminders to the selected recipients')]
class SendTeachingReminders extends Command
{
    public function handle(TeachingReminderService $reminders): int
    {
        $sent = 0;
        $failed = 0;

        foreach (['teacher' => 'reminder_email_sent_at', 'student' => 'student_reminder_email_sent_at'] as $recipientType => $sentColumn) {
            foreach ($reminders->dueQuery()->where("remind_{$recipientType}_by_email", true)->whereNull($sentColumn)->lazyById(100) as $candidate) {
                try {
                    $sent += DB::transaction(function () use ($candidate, $reminders, $recipientType, $sentColumn): int {
                        $entry = $reminders->dueQuery()
                            ->whereKey($candidate->id)
                            ->where("remind_{$recipientType}_by_email", true)
                            ->whereNull($sentColumn)
                            ->lockForUpdate()
                            ->first();

                        if (! $entry) {
                            return 0;
                        }

                        $entry->load(['teachingCourse.user', 'user']);
                        $course = $entry->teachingCourse;
                        $teacher = $course?->user;

                        if (! $teacher || ! $teacher->is_active
                            || $teacher->school_id !== $course->school_id
                            || ! $teacher->hasAnyRole(['super_admin', 'admin', 'teaching_admin', 'teacher'])) {
                            return 0;
                        }

                        $recipient = $recipientType === 'teacher' ? $teacher : $entry->user;

                        if (! $recipient || blank($recipient->email) || ! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
                            return 0;
                        }

                        if ($recipientType === 'student' && Import116Job::isPlaceholderEmail($recipient->email)) {
                            return 0;
                        }

                        Mail::to($recipient->email)->send(new TeachingReminderMail($entry));
                        $entry->forceFill([$sentColumn => now()])->save();

                        return 1;
                    });
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                }
            }
        }

        $this->info("Sent {$sent} teaching reminders; {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
