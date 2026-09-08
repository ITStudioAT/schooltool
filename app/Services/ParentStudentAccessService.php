<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class ParentStudentAccessService
{
    private const MAXIMUM_CHILD_AGE = 17;

    private const MAXIMUM_CODE_ATTEMPTS = 5;

    private const SESSION_KEY = 'student_parent_access';

    /** @return Collection<int, Import116> */
    public function eligibleStudents(string $email, int $schoolId, int $schoolyearId): Collection
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $earliestBirthDate = today()->subYearsNoOverflow(self::MAXIMUM_CHILD_AGE + 1)->addDay();
        $hasActiveCourse = function (Builder $query) use ($schoolId, $schoolyearId): void {
            $query->whereNull('canceled_at')
                ->whereHas('teachingCourse', function (Builder $courseQuery) use ($schoolId, $schoolyearId): void {
                    $courseQuery->where('school_id', $schoolId)
                        ->where('schoolyear_id', $schoolyearId);
                });
        };

        return Import116::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereNotNull('exists_date')
            ->whereBetween('birth_date', [$earliestBirthDate, today()])
            ->where(function ($query) use ($normalizedEmail): void {
                $query->whereRaw('LOWER(TRIM(mother_email)) = ?', [$normalizedEmail])
                    ->orWhereRaw('LOWER(TRIM(father_email)) = ?', [$normalizedEmail]);
            })
            ->where(function (Builder $query) use ($hasActiveCourse): void {
                $query->whereHas('teachingCourseStudents', $hasActiveCourse)
                    ->orWhereHas('user.teachingCourseStudents', $hasActiveCourse)
                    ->orWhereHas('linkedUsers.teachingCourseStudents', $hasActiveCourse);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }

    public function startChallenge(string $email, int $schoolId, int $schoolyearId): void
    {
        $token = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes((int) config('schooltool.token_expire_time'));
        $school = School::query()->findOrFail($schoolId);

        $this->clear();
        session()->put(self::SESSION_KEY, [
            'school_id' => $schoolId,
            'schoolyear_id' => $schoolyearId,
            'email' => $this->normalizeEmail($email),
            'token_hash' => Hash::make($token),
            'expires_at' => $expiresAt->timestamp,
            'attempts_remaining' => self::MAXIMUM_CODE_ATTEMPTS,
            'verified_at' => null,
            'student_import_id' => null,
        ]);

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/'.$school->logo),
            'subject' => 'Ihr Eltern-Login-Code für das Unterrichtstool',
            'markdown' => 'mails.homepage.sendCode',
            'token_2fa' => $token,
            'token-expire-time' => config('schooltool.token_expire_time'),
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($email))->notify(new StandardEmail($mail));
    }

    public function verifyChallenge(
        string $token,
        string $email,
        int $schoolId,
        int $schoolyearId,
    ): bool {
        $challenge = $this->challenge();

        if (! $this->challengeMatches($challenge, $email, $schoolId, $schoolyearId)) {
            $this->clear();

            return false;
        }

        $attemptsRemaining = (int) ($challenge['attempts_remaining'] ?? 0);
        $tokenHash = (string) ($challenge['token_hash'] ?? '');

        if ($attemptsRemaining < 1 || $tokenHash === '' || ! Hash::check($token, $tokenHash)) {
            $challenge['attempts_remaining'] = max(0, $attemptsRemaining - 1);
            session()->put(self::SESSION_KEY, $challenge);

            return false;
        }

        $challenge['token_hash'] = null;
        $challenge['verified_at'] = now()->timestamp;
        session()->put(self::SESSION_KEY, $challenge);

        return true;
    }

    /** @return Collection<int, Import116> */
    public function verifiedStudents(): Collection
    {
        $challenge = $this->verifiedChallenge();
        if (! $challenge) {
            return new Collection;
        }

        return $this->eligibleStudents(
            (string) $challenge['email'],
            (int) $challenge['school_id'],
            (int) $challenge['schoolyear_id'],
        );
    }

    public function selectStudent(int $studentImportId, StudentService $studentService): ?User
    {
        $challenge = $this->verifiedChallenge();
        if (! $challenge) {
            return null;
        }

        $importStudent = $this->eligibleStudents(
            (string) $challenge['email'],
            (int) $challenge['school_id'],
            (int) $challenge['schoolyear_id'],
        )->firstWhere('id', $studentImportId);

        if (! $importStudent) {
            return null;
        }

        $existingUser = $studentService->existingUserForImport116($importStudent);
        if ($existingUser && ! $this->isActiveStudentUser($existingUser, false)) {
            return null;
        }

        $student = $studentService->createUserFromImport116($importStudent);
        if (! $this->isActiveStudentUser($student)) {
            return null;
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $challenge['student_import_id'] = $studentImportId;
        $challenge['expires_at'] = now()->addMinutes((int) config('session.lifetime', 120))->timestamp;
        session()->put(self::SESSION_KEY, $challenge);
        session()->regenerate();

        return $student;
    }

    public function currentStudent(): ?User
    {
        $authenticatedStudent = $this->authenticatedStudent();
        if ($authenticatedStudent) {
            return $authenticatedStudent;
        }

        return $this->parentStudent();
    }

    public function authenticatedStudent(): ?User
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User || ! $this->isActiveStudentUser($user)) {
            return null;
        }

        return $user;
    }

    public function isParentViewer(): bool
    {
        return $this->parentStudent() !== null;
    }

    public function selectedStudentImportId(): ?int
    {
        $challenge = $this->verifiedChallenge();
        if (! $challenge) {
            return null;
        }

        $studentImportId = (int) ($challenge['student_import_id'] ?? 0);

        return $studentImportId > 0 ? $studentImportId : null;
    }

    public function isActiveStudentUser(User $user, bool $requireStudentRole = true): bool
    {
        $isActive = $user->is_active === null || (bool) $user->is_active;

        return $isActive && (! $requireStudentRole || $user->hasRole('student'));
    }

    public function school(): ?School
    {
        $challenge = $this->challenge();
        if (! $challenge || ! $this->challengeIsCurrent($challenge)) {
            return null;
        }

        return School::query()->find((int) $challenge['school_id']);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /** @return array<string, int|string|null>|null */
    private function challenge(): ?array
    {
        $challenge = session(self::SESSION_KEY);

        return is_array($challenge) ? $challenge : null;
    }

    /** @return array<string, int|string|null>|null */
    private function verifiedChallenge(): ?array
    {
        $challenge = $this->challenge();
        if (! $challenge || ! $this->challengeIsCurrent($challenge) || ! ($challenge['verified_at'] ?? null)) {
            $this->clear();

            return null;
        }

        return $challenge;
    }

    /** @param array<string, int|string|null>|null $challenge */
    private function challengeMatches(?array $challenge, string $email, int $schoolId, int $schoolyearId): bool
    {
        if (! $challenge || ! $this->challengeIsCurrent($challenge)) {
            return false;
        }

        return (int) ($challenge['school_id'] ?? 0) === $schoolId
            && (int) ($challenge['schoolyear_id'] ?? 0) === $schoolyearId
            && hash_equals((string) ($challenge['email'] ?? ''), $this->normalizeEmail($email));
    }

    /** @param array<string, int|string|null> $challenge */
    private function challengeIsCurrent(array $challenge): bool
    {
        if ((int) ($challenge['expires_at'] ?? 0) <= now()->timestamp) {
            return false;
        }

        $activeSchoolyearId = SchoolTool::query()
            ->where('school_id', (int) ($challenge['school_id'] ?? 0))
            ->value('active_schoolyear_id');

        return (int) $activeSchoolyearId === (int) ($challenge['schoolyear_id'] ?? 0);
    }

    private function parentStudent(): ?User
    {
        $challenge = $this->verifiedChallenge();
        if (! $challenge) {
            return null;
        }

        $studentImportId = (int) ($challenge['student_import_id'] ?? 0);
        if ($studentImportId < 1) {
            return null;
        }

        $importStudent = $this->eligibleStudents(
            (string) $challenge['email'],
            (int) $challenge['school_id'],
            (int) $challenge['schoolyear_id'],
        )->firstWhere('id', $studentImportId);

        if (! $importStudent) {
            $this->clear();

            return null;
        }

        $student = User::query()
            ->where('school_id', (int) $importStudent->school_id)
            ->where(function ($query) use ($importStudent): void {
                $query->where('import116_id', (int) $importStudent->id);

                if ((int) ($importStudent->user_id ?? 0) > 0) {
                    $query->orWhere('id', (int) $importStudent->user_id);
                }
            })
            ->first();

        if (! $student || ! $this->isActiveStudentUser($student)) {
            $this->clear();

            return null;
        }

        return $student;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
