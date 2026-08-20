<?php

namespace App\Services\StudentsTimetables;

use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentTimetablePublishedTimetable;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentTimetablePublishedTimetableService
{
    private const int MAX_NAME_ATTEMPTS = 100;

    private const string NAME_UNIQUE_INDEX = 'student_tt_published_school_name_unique';

    /**
     * @param  array<string, mixed>  $timetable
     * @param  array<string, mixed>|null  $state
     */
    public function publish(
        User $user,
        Import116 $student,
        array $timetable,
        ?array $state,
    ): StudentTimetablePublishedTimetable {
        $schoolyearPrefix = $this->schoolyearPrefix($user);

        for ($attempt = 0; $attempt < self::MAX_NAME_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $student, $timetable, $state, $schoolyearPrefix): StudentTimetablePublishedTimetable {
                    School::query()
                        ->whereKey($user->school_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $publishedTimetable = StudentTimetablePublishedTimetable::query()
                        ->where('school_id', $user->school_id)
                        ->where('schoolyear_id', $user->schoolyear_id)
                        ->where('student_code', $student->student_code)
                        ->lockForUpdate()
                        ->first();

                    if (! $publishedTimetable) {
                        $publishedTimetable = new StudentTimetablePublishedTimetable([
                            'school_id' => $user->school_id,
                            'schoolyear_id' => $user->schoolyear_id,
                            'student_code' => (string) $student->student_code,
                        ]);
                    }

                    if (! preg_match('/^\d{2}[A-Z]{3}$/', (string) $publishedTimetable->name)) {
                        $publishedTimetable->name = $schoolyearPrefix.$this->randomUppercaseSuffix();
                    }

                    $studentLabel = trim("{$student->last_name} {$student->first_name}")
                        ?: (string) $student->student_code;

                    $publishedTimetable->fill([
                        'published_by_user_id' => $user->id,
                        'student_label' => $studentLabel,
                        'timetable' => $timetable,
                        'state' => $state,
                        'published_at' => now(),
                    ])->save();

                    return $publishedTimetable->refresh();
                }, attempts: 5);
            } catch (UniqueConstraintViolationException $exception) {
                if (! $this->isNameCollision($exception)) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'timetable_name' => 'Für diesen Stundenplan konnte kein eindeutiger Name vergeben werden.',
        ]);
    }

    private function schoolyearPrefix(User $user): string
    {
        $schoolyear = Schoolyear::query()
            ->where('school_id', $user->school_id)
            ->whereKey($user->schoolyear_id)
            ->first();

        if (! $schoolyear) {
            throw ValidationException::withMessages([
                'schoolyear' => 'Das ausgewählte Schuljahr wurde nicht gefunden.',
            ]);
        }

        $from = trim((string) $schoolyear->from);

        if (preg_match('/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})$/', $from, $matches)
            && checkdate((int) $matches['month'], (int) $matches['day'], (int) $matches['year'])) {
            return substr($matches['year'], -2);
        }

        foreach ([$schoolyear->name, $schoolyear->concerns] as $schoolyearLabel) {
            if (preg_match('/(?<!\d)(?<year>\d{4}|\d{2})\s*\/\s*\d{2,4}(?!\d)/u', (string) $schoolyearLabel, $matches)) {
                return substr($matches['year'], -2);
            }
        }

        throw ValidationException::withMessages([
            'schoolyear' => 'Aus dem ausgewählten Schuljahr konnte kein Startjahr ermittelt werden.',
        ]);
    }

    private function randomUppercaseSuffix(): string
    {
        $suffix = '';

        for ($attempt = 0; $attempt < 10 && strlen($suffix) < 3; $attempt++) {
            $randomLetters = preg_replace('/[^A-Za-z]/', '', Str::random(6)) ?? '';
            $suffix .= strtoupper($randomLetters);
        }

        if (strlen($suffix) < 3) {
            throw ValidationException::withMessages([
                'timetable_name' => 'Für diesen Stundenplan konnte kein Name erzeugt werden.',
            ]);
        }

        return substr($suffix, 0, 3);
    }

    private function isNameCollision(UniqueConstraintViolationException $exception): bool
    {
        if ($exception->index === self::NAME_UNIQUE_INDEX) {
            return true;
        }

        return count(array_intersect($exception->columns, ['school_id', 'name'])) === 2;
    }
}
