<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RecordsCreateService
{
    public function initRecords(): void
    {
        $school = $this->firstOrCreateSchool();
        $this->firstOrCreateSchoolyear($school);
        $this->checkOrCreateAdminRoles();
        $this->checkOrCreateLicences();

        foreach (School::all() as $school) {
            $this->checkOrCreateAdmins($school);
            $this->checkOrCreateSchoolyears($school);
            $this->checkOrCreateSchoolTool($school);
        }
    }

    private function checkOrCreateLicences(): void
    {
        $licences = config('schooltool.licences', []);

        foreach ($licences as $licence) {
            $name = $licence['name'] ?? null;

            if (! $name) {
                continue;
            }

            $attributes = $this->normalizeLicenceAttributes(
                $name,
                collect($licence)->except('name')->toArray()
            );

            $licence = Licence::firstOrNew(['name' => $name]);

            if ($licence->exists) {
                $licence->fill($this->seededLicenceAttributesForExistingLicence($attributes));
                $licence->save();

                continue;
            }

            $licence->fill($attributes);
            $licence->save();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function seededLicenceAttributesForExistingLicence(array $attributes): array
    {
        return collect($attributes)
            ->except([
                'licence_model',
                'licence_schema_version',
                'school_licence_enabled',
                'school_price_per_year',
                'school_included_storage_gb',
                'school_extra_storage_step_gb',
                'school_extra_storage_step_price',
                'admin_licence_enabled',
                'admin_price_per_year',
                'admin_role_names',
                'admin_included_storage_gb',
                'admin_extra_storage_step_gb',
                'admin_extra_storage_step_price',
                'user_licence_enabled',
                'user_price_per_year',
                'user_role_names',
                'user_included_storage_gb',
                'user_extra_storage_step_gb',
                'user_extra_storage_step_price',
            ])
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeLicenceAttributes(string $licenceName, array $attributes): array
    {
        $startDayMonth = $this->normalizeDayMonthForStorage($attributes['start_day_month'] ?? null);
        $endDayMonth = array_key_exists('end_day_month', $attributes)
            ? $this->normalizeDayMonthForStorage($attributes['end_day_month'])
            : $this->deriveEndDayMonthForStorage($startDayMonth);

        $this->assertValidStartDayMonth($licenceName, $startDayMonth);
        $this->assertValidEndDayMonth($licenceName, $endDayMonth);

        $attributes['start_day_month'] = $startDayMonth;
        $attributes['end_day_month'] = $endDayMonth;

        return $attributes;
    }

    private function normalizeDayMonthForStorage(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^(0[1-9]|[12][0-9]|3[01])\.(0[1-9]|1[0-2])\.?$/', $normalized, $matches) === 1) {
            if (! checkdate((int) $matches[2], (int) $matches[1], 2001)) {
                return $normalized;
            }

            return sprintf('%s-%s', $matches[2], $matches[1]);
        }

        return $normalized;
    }

    private function deriveEndDayMonthForStorage(mixed $startDayMonth): mixed
    {
        if (! $this->isValidStorageDayMonth($startDayMonth)) {
            return null;
        }

        [$startMonth, $startDay] = array_map('intval', explode('-', (string) $startDayMonth));
        $currentDate = \DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('2001-%02d-%02d', $startMonth, $startDay));

        if (! $currentDate instanceof \DateTimeImmutable) {
            return null;
        }

        return $currentDate->modify('-1 day')->format('m-d');
    }

    private function isValidStorageDayMonth(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        if (preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $value, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[1], (int) $matches[2], 2001);
    }

    private function assertValidStartDayMonth(string $licenceName, mixed $value): void
    {
        if ($this->isValidStorageDayMonth($value)) {
            return;
        }

        throw new \UnexpectedValueException(sprintf(
            'Licence [%s] has invalid start_day_month [%s] in config(schooltool.licences). Expected TT.MM. or MM-DD.',
            $licenceName,
            is_scalar($value) ? (string) $value : gettype($value),
        ));
    }

    private function assertValidEndDayMonth(string $licenceName, mixed $value): void
    {
        if ($value === null || $this->isValidStorageDayMonth($value)) {
            return;
        }

        throw new \UnexpectedValueException(sprintf(
            'Licence [%s] has invalid end_day_month [%s] in config(schooltool.licences). Expected TT.MM. or MM-DD.',
            $licenceName,
            is_scalar($value) ? (string) $value : gettype($value),
        ));
    }

    private function firstOrCreateSchool(): School
    {
        $school = School::first();

        if ($school) {
            return $school;
        }

        $school = School::create([
            'long_name' => 'Christian-Doppler-Gymnasium Salzburg',
            'short_name' => 'CDGym',
            'logo' => 'logo_1.png',
            'is_selectable' => 1,
        ]);

        $this->copyDefaultLogo();

        return $school;
    }

    private function copyDefaultLogo(): void
    {
        $sourceLogo = storage_path('app/public/images/logo.png');
        $destLogo = storage_path('app/public/images/logos/logo_1.png');

        if (! file_exists($sourceLogo)) {
            return;
        }

        $destDir = dirname($destLogo);
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        copy($sourceLogo, $destLogo);
    }

    private function checkOrCreateSchoolyears(School $school): void
    {
        $schoolyears = config('schooltool.schoolyears', []);

        foreach ($schoolyears as $schoolyear) {
            $concerns = $this->resolveSchoolyearConcerns($schoolyear);

            Schoolyear::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'concerns' => $concerns,
                ],
                [
                    'name' => $schoolyear['name'] ?? null,
                    'from' => $schoolyear['from'] ?? null,
                    'until' => $schoolyear['to'] ?? null,
                    'sem_2_start' => $schoolyear['sem_2_start'] ?? null,
                ]
            );
        }
    }

    private function resolveSchoolyearConcerns(array $schoolyear): ?string
    {
        if (! empty($schoolyear['concerns'])) {
            return (string) $schoolyear['concerns'];
        }

        if (! empty($schoolyear['name'])) {
            return (string) $schoolyear['name'];
        }

        $fromYear = $this->extractYear($schoolyear['from'] ?? null);
        $toYear = $this->extractYear($schoolyear['to'] ?? null);

        if ($fromYear !== null && $toYear !== null) {
            return $fromYear.'/'.substr((string) $toYear, -2);
        }

        return null;
    }

    private function extractYear(mixed $value): ?int
    {
        if (! is_string($value) || strlen($value) < 4) {
            return null;
        }

        $year = (int) substr($value, 0, 4);

        return $year > 0 ? $year : null;
    }

    private function firstOrCreateSchoolyear(School $school): Schoolyear
    {
        return Schoolyear::firstOrCreate(
            ['school_id' => $school->id],
            [
                'name' => 'Schuljahr 2025/26',
                'from' => '2025-09-08',
                'until' => '2026-07-10',
                'sem_2_start' => '2026-02-16',
                'concerns' => '2025/26',
            ]
        );
    }

    private function firstOrCreateSchoolTool(School $school): SchoolTool
    {
        return SchoolTool::firstOrCreate(
            ['school_id' => $school->id],
            []
        );
    }

    private function checkOrCreateAdminRoles(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'studentstimetables_admin', 'guard_name' => 'web']);
    }

    private function checkOrCreateAdmins(School $school): void
    {
        $schoolyear = Schoolyear::where('school_id', $school->id)->first();
        $this->checkOrCreateAdmin($school, $schoolyear, 'kron@naturwelt.at', 'super_admin');
    }

    private function checkOrCreateAdmin(School $school, ?Schoolyear $schoolyear, string $email, string $role): User
    {
        $user = User::where('school_id', $school->id)->where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'school_id' => $school->id,
                'schoolyear_id' => $schoolyear?->id,
                'email' => $email,
                'password' => $this->superAdminPassword(),
                'first_name' => 'Günther',
                'last_name' => 'Kron',
            ]);

            $user->email_verified_at = now();
            $user->confirmed_at = now();
            $user->is_active = 1;
            $user->save();
        }

        $user->assignRole($role);

        return $user;
    }

    private function superAdminPassword(): string
    {
        $configuredPassword = config('schooltool.sa_pw');

        return is_string($configuredPassword) && $configuredPassword !== ''
            ? $configuredPassword
            : Hash::make(Str::random(64));
    }

    public function checkOrCreateSchoolTool(School $school): SchoolTool
    {
        return SchoolTool::firstOrCreate(
            ['school_id' => $school->id],
            []
        );
    }
}
