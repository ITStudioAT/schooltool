<?php

namespace App\Services;

use App\Jobs\Teaching\Import116Job;
use App\Mail\TeachingCourseStudentEntryNotificationMail;
use App\Models\Import116;
use App\Models\TeachingClassHeadEmail;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseStudentEntryNotification;
use App\Models\TeachingEntryDefinition;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class TeachingCourseStudentEntryNotificationService
{
    /**
     * @return array<int, array{
     *     key: string,
     *     notification_id: ?int,
     *     recipient_type: string,
     *     group_label: string,
     *     recipient_label: string,
     *     email: ?string,
     *     available: bool,
     *     informed_at: ?string,
     *     opened_at: ?string,
     *     confirmed_at: ?string,
     *     confirmation_method: ?string,
     *     confirmed_by: ?string,
     * }>
     */
    public function recipients(TeachingCourseStudentEntry $entry): array
    {
        $course = $entry->teachingCourse;
        abort_unless($course, 422, 'Der Kurs für diesen Eintrag ist nicht verfügbar.');

        $student = $entry->user;
        abort_unless($student, 422, 'Die Schülerdaten für diesen Eintrag sind nicht verfügbar.');

        $definition = $this->notificationDefinition($course, $entry->type);
        $availableRecipients = collect($this->resolveRecipients(
            $course,
            $student,
            $this->configuredRecipientTypes($definition),
        ));
        $notifications = $entry->notifications()->with('confirmedBy:id,first_name,last_name,email')->get()->keyBy(
            fn (TeachingCourseStudentEntryNotification $notification): string => $this->recipientKey(
                $notification->recipient_type,
                $notification->email,
            )
        );

        $recipients = $availableRecipients
            ->map(function (array $recipient) use ($notifications): array {
                $notification = $notifications->get($recipient['key']);

                return $this->recipientPayload($recipient, $notification);
            });

        $availableKeys = $availableRecipients->pluck('key');
        $historicalRecipients = $notifications
            ->reject(fn (TeachingCourseStudentEntryNotification $notification): bool => $availableKeys->contains(
                $this->recipientKey($notification->recipient_type, $notification->email)
            ))
            ->map(fn (TeachingCourseStudentEntryNotification $notification): array => $this->recipientPayload([
                'key' => $this->recipientKey($notification->recipient_type, $notification->email),
                'recipient_type' => $notification->recipient_type,
                'group_label' => $this->groupLabel($notification->recipient_type),
                'recipient_label' => $notification->recipient_label,
                'email' => $notification->email,
                'available' => false,
            ], $notification));

        return $recipients
            ->concat($historicalRecipients)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     notification_id: ?int,
     *     recipient_type: string,
     *     group_label: string,
     *     recipient_label: string,
     *     email: ?string,
     *     available: bool,
     *     informed_at: ?string,
     *     opened_at: ?string,
     *     confirmed_at: ?string,
     *     confirmation_method: ?string,
     *     confirmed_by: ?string,
     * }>
     */
    public function preview(TeachingCourse $course, User $student, string $type): array
    {
        $definition = $this->notificationDefinition($course, $type);

        return collect($this->resolveRecipients(
            $course,
            $student,
            $this->configuredRecipientTypes($definition),
        ))
            ->map(fn (array $recipient): array => $this->recipientPayload($recipient, null))
            ->values()
            ->all();
    }

    /** @param array<int, string> $recipientKeys */
    public function send(TeachingCourseStudentEntry $entry, array $recipientKeys): array
    {
        $course = $entry->teachingCourse;
        abort_unless($course, 422, 'Der Kurs für diesen Eintrag ist nicht verfügbar.');

        $student = $entry->user;
        abort_unless($student, 422, 'Die Schülerdaten für diesen Eintrag sind nicht verfügbar.');

        $definition = $this->notificationDefinition($course, $entry->type);
        $recipients = collect($this->resolveRecipients(
            $course,
            $student,
            $this->configuredRecipientTypes($definition),
        ))
            ->where('available', true)
            ->whereIn('key', $recipientKeys)
            ->values();

        abort_if($recipients->count() !== count(array_unique($recipientKeys)), 422, 'Mindestens eine ausgewählte E-Mail-Adresse ist nicht mehr verfügbar.');

        foreach ($recipients as $recipient) {
            $notification = TeachingCourseStudentEntryNotification::query()->updateOrCreate([
                'teaching_course_student_entry_id' => $entry->id,
                'recipient_type' => $recipient['recipient_type'],
                'email' => $recipient['email'],
            ], [
                'recipient_label' => $recipient['recipient_label'],
            ]);

            Mail::to($notification->email)->send(new TeachingCourseStudentEntryNotificationMail(
                notification: $notification,
                entry: $entry,
                definition: $definition,
            ));

            $notification->forceFill(['informed_at' => now()])->save();
        }

        return $this->recipients($entry->fresh());
    }

    /** @return array<int, array<string, mixed>> */
    public function confirmManually(
        TeachingCourseStudentEntry $entry,
        TeachingCourseStudentEntryNotification $notification,
        User $confirmedBy,
    ): array {
        abort_unless(
            (int) $notification->teaching_course_student_entry_id === (int) $entry->id,
            404,
        );
        abort_unless($notification->informed_at, 422, 'Eine nicht versendete Verständigung kann nicht bestätigt werden.');

        if (! $notification->confirmed_at) {
            $notification->forceFill([
                'confirmed_at' => now(),
                'confirmation_method' => 'manual',
                'confirmed_by_user_id' => $confirmedBy->id,
                'confirmed_by_label' => $confirmedBy->full_name,
            ])->save();
        }

        return $this->recipients($entry);
    }

    /** @return array<int, string> */
    private function configuredRecipientTypes(TeachingEntryDefinition $definition): array
    {
        return collect($definition->notification_recipients ?? [])
            ->filter(fn (mixed $recipientType): bool => in_array($recipientType, ['class_teacher', 'parents', 'student'], true))
            ->values()
            ->all();
    }

    private function notificationDefinition(TeachingCourse $course, string $type): TeachingEntryDefinition
    {
        abort_unless($course->teaching_entry_area_id, 422, 'Für diesen Eintrag sind keine Verständigungen konfiguriert.');

        $definition = TeachingEntryDefinition::query()
            ->where('teaching_entry_area_id', $course->teaching_entry_area_id)
            ->where('school_id', $course->school_id)
            ->where('schoolyear_id', $course->schoolyear_id)
            ->where('user_id', $course->user_id)
            ->where('short_name', $type)
            ->where('has_notifications', true)
            ->first();

        abort_unless($definition, 422, 'Für diesen Eintrag sind keine Verständigungen konfiguriert.');

        return $definition;
    }

    /**
     * @param  array<int, string>  $configuredTypes
     * @return array<int, array{
     *     key: string,
     *     recipient_type: string,
     *     group_label: string,
     *     recipient_label: string,
     *     email: ?string,
     *     available: bool,
     * }>
     */
    private function resolveRecipients(TeachingCourse $course, User $student, array $configuredTypes): array
    {
        $student->loadMissing('import116');
        $import = $student->import116;
        $recipients = [];

        foreach ($configuredTypes as $recipientType) {
            $resolvedForType = match ($recipientType) {
                'class_teacher' => $this->classTeacherRecipients($course, $student, $import),
                'parents' => $this->parentRecipients($import),
                'student' => $this->studentRecipients($student, $import),
                default => [],
            };

            if ($resolvedForType === []) {
                $recipients[] = $this->missingRecipient($recipientType);

                continue;
            }

            array_push($recipients, ...$resolvedForType);
        }

        return collect($recipients)
            ->unique(fn (array $recipient): string => $recipient['key'])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function classTeacherRecipients(TeachingCourse $course, User $student, ?Import116 $import): array
    {
        $className = trim((string) ($import?->class ?: $student->schoolclass));
        if ($className === '') {
            return [];
        }

        $classHeadEmail = TeachingClassHeadEmail::query()
            ->where('school_id', $course->school_id)
            ->where('schoolyear_id', $course->schoolyear_id)
            ->where('user_id', $course->user_id)
            ->where('class_name', $className)
            ->first();
        if (! $classHeadEmail) {
            return [];
        }

        return collect([$classHeadEmail->email_1, $classHeadEmail->email_2])
            ->filter(fn (mixed $email): bool => $this->isUsableEmail($email))
            ->values()
            ->map(fn (string $email, int $index): array => $this->availableRecipient(
                'class_teacher',
                $index === 0 ? 'Klassenvorstand' : 'Klassenvorstand '.($index + 1),
                $email,
            ))
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function parentRecipients(?Import116 $import): array
    {
        if (! $import) {
            return [];
        }

        return collect([
            ['label' => trim((string) $import->mother_name) ?: 'Mutter', 'email' => $import->mother_email],
            ['label' => trim((string) $import->father_name) ?: 'Vater', 'email' => $import->father_email],
        ])
            ->filter(fn (array $parent): bool => $this->isUsableEmail($parent['email']))
            ->map(fn (array $parent): array => $this->availableRecipient('parents', $parent['label'], $parent['email']))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function studentRecipients(User $student, ?Import116 $import): array
    {
        $email = $this->isUsableEmail($import?->email)
            ? $import?->email
            : $student->email;

        if (! $this->isUsableEmail($email) || Import116Job::isPlaceholderEmail((string) $email)) {
            return [];
        }

        return [$this->availableRecipient('student', trim("{$student->first_name} {$student->last_name}") ?: 'Schüler:in', $email)];
    }

    /** @return array{key: string, recipient_type: string, group_label: string, recipient_label: string, email: string, available: bool} */
    private function availableRecipient(string $recipientType, string $recipientLabel, string $email): array
    {
        $normalizedEmail = mb_strtolower(trim($email));

        return [
            'key' => $this->recipientKey($recipientType, $normalizedEmail),
            'recipient_type' => $recipientType,
            'group_label' => $this->groupLabel($recipientType),
            'recipient_label' => $recipientLabel,
            'email' => $normalizedEmail,
            'available' => true,
        ];
    }

    /** @return array{key: string, recipient_type: string, group_label: string, recipient_label: string, email: null, available: bool} */
    private function missingRecipient(string $recipientType): array
    {
        return [
            'key' => hash('sha256', "missing|{$recipientType}"),
            'recipient_type' => $recipientType,
            'group_label' => $this->groupLabel($recipientType),
            'recipient_label' => 'Keine E-Mail-Adresse vorhanden',
            'email' => null,
            'available' => false,
        ];
    }

    private function groupLabel(string $recipientType): string
    {
        return match ($recipientType) {
            'class_teacher' => 'Klassenvorstand',
            'parents' => 'Eltern',
            'student' => 'Schüler:in',
            default => 'Empfänger:in',
        };
    }

    private function recipientKey(string $recipientType, string $email): string
    {
        return hash('sha256', $recipientType.'|'.mb_strtolower(trim($email)));
    }

    private function isUsableEmail(mixed $email): bool
    {
        return filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param  array{key: string, recipient_type: string, group_label: string, recipient_label: string, email: ?string, available: bool}  $recipient
     * @return array{key: string, notification_id: ?int, recipient_type: string, group_label: string, recipient_label: string, email: ?string, available: bool, informed_at: ?string, opened_at: ?string, confirmed_at: ?string, confirmation_method: ?string, confirmed_by: ?string}
     */
    private function recipientPayload(array $recipient, ?TeachingCourseStudentEntryNotification $notification): array
    {
        return [
            ...$recipient,
            'notification_id' => $notification?->id,
            'informed_at' => $notification?->informed_at?->toIso8601String(),
            'opened_at' => $notification?->opened_at?->toIso8601String(),
            'confirmed_at' => $notification?->confirmed_at?->toIso8601String(),
            'confirmation_method' => $notification?->confirmation_method,
            'confirmed_by' => $notification?->confirmed_by_label ?: match ($notification?->confirmation_method) {
                'email' => $notification?->recipient_label,
                'manual' => $notification?->confirmedBy?->full_name,
                default => null,
            },
        ];
    }
}
