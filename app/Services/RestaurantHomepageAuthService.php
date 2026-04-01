<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\StandardEmail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RestaurantHomepageAuthService
{
    public function __construct(
        public Import116Service $import116Service,
        public TeacherListService $teacherListService,
        public UserService $userService,
    ) {}

    /**
     * @param  array{school_id:int|string, email:string}  $data
     * @return array<string, mixed>
     */
    public function checkEmail(array $data): array
    {
        $schoolId = (int) $data['school_id'];
        $normalizedEmail = $this->normalizeEmail($data['email']);

        $directUser = User::query()
            ->bySchoolAndRole($schoolId, 'lunch_user')
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->orderBy('id')
            ->first();

        if ($directUser) {
            return [
                'status' => 'USER_FOUND',
                'school_id' => $schoolId,
                'email' => $normalizedEmail,
                'match_source' => 'user',
                'matched_users_count' => 1,
                'matched_users' => [$this->serializeMatchedUser($directUser)],
                'available_auth_methods' => ['code', 'password'],
            ];
        }

        $matchedUsers = $this->matchedUsersFromParentEmail($schoolId, $normalizedEmail);

        if ($matchedUsers->isNotEmpty()) {
            return [
                'status' => 'USER_FOUND',
                'school_id' => $schoolId,
                'email' => $normalizedEmail,
                'match_source' => 'parent',
                'matched_users_count' => $matchedUsers->count(),
                'matched_users' => $matchedUsers
                    ->map(fn (User $user): array => $this->serializeMatchedUser($user))
                    ->values()
                    ->all(),
                'available_auth_methods' => ['code', 'password'],
            ];
        }

        $context = $this->resolveRegistrationContext($schoolId, $normalizedEmail);

        return [
            'status' => 'REGISTER_REQUIRED',
            'school_id' => $schoolId,
            'email' => $normalizedEmail,
            'match_source' => $context['registration_source'],
            'registration_source' => $context['registration_source'],
            ...$this->registrationContextPayload($context, $normalizedEmail),
        ];
    }

    /**
     * @param  array{school_id:int|string, email:string, first_name?:?string, last_name?:?string, confirmation_token?:?string}  $data
     * @return array<string, mixed>
     */
    public function register(array $data): array
    {
        $schoolId = (int) $data['school_id'];
        $normalizedEmail = $this->normalizeEmail($data['email']);
        $normalizedFirstName = $this->normalizeNullableString($data['first_name'] ?? null);
        $normalizedLastName = $this->normalizeNullableString($data['last_name'] ?? null);
        $confirmationToken = $this->normalizeNullableString($data['confirmation_token'] ?? null);
        $context = $this->resolveRegistrationContext($schoolId, $normalizedEmail);

        return DB::transaction(function () use ($context, $schoolId, $normalizedEmail, $normalizedFirstName, $normalizedLastName, $confirmationToken): array {
            $registrationSource = (string) $context['registration_source'];

            if ($confirmationToken !== null && $registrationSource !== 'new_user') {
                throw ValidationException::withMessages([
                    'data.email' => 'Die Registrierung muss neu gestartet werden.',
                ]);
            }

            if ($registrationSource === 'new_user') {
                if ($confirmationToken === null) {
                    $this->sendManualRegistrationCode($schoolId, $normalizedEmail);

                    return [
                        'status' => 'CONFIRM_EMAIL',
                        'school_id' => $schoolId,
                        'email' => $normalizedEmail,
                        'registration_source' => $registrationSource,
                        'requires_email_confirmation' => true,
                        'message' => 'Wir haben einen 6-stelligen Code an Ihre E-Mail-Adresse gesendet. Bitte bestaetigen Sie zuerst Ihre E-Mail-Adresse.',
                    ];
                }

                $this->ensureManualNameInput($normalizedFirstName, $normalizedLastName);
                $this->ensureManualRegistrationConfirmed($schoolId, $normalizedEmail, $confirmationToken);

                $user = $this->registerManualUser($schoolId, $normalizedEmail, $normalizedFirstName, $normalizedLastName);
                $this->completeVerifiedManualRestaurantRegistration($user);
                $this->forgetManualRegistration($schoolId, $normalizedEmail);

                return [
                    'status' => 'REGISTERED',
                    'school_id' => $schoolId,
                    'email' => $normalizedEmail,
                    'user_id' => (int) $user->id,
                    'registration_source' => $registrationSource,
                    'requires_email_confirmation' => false,
                    'message' => 'Das Mittagskonto wurde angelegt und wartet jetzt auf die Freischaltung.',
                ];
            }

            $user = match ($registrationSource) {
                'existing_user' => $this->registerExistingUser($context),
                'teacher_list' => $this->registerTeacherUser($schoolId, $normalizedEmail),
                'import116_student' => $this->registerImportStudentUser($context),
                'import116_parent' => $this->registerImportParentUser($schoolId, $normalizedEmail, $context, $normalizedFirstName, $normalizedLastName),
                'new_user' => $this->registerManualUser($schoolId, $normalizedEmail, $normalizedFirstName, $normalizedLastName),
                default => throw ValidationException::withMessages([
                    'data.email' => 'Die E-Mail-Adresse kann nicht verarbeitet werden.',
                ]),
            };

            if ($registrationSource === 'existing_user' && $user->hasRole('lunch_candidate') && ! $user->hasRole('lunch_user')) {
                if ($this->newUsersMustConfirmEmail($schoolId)) {
                    $this->preparePendingRestaurantUser($user);
                    $this->userService->sendCode($user, 'E-Mail bestaetigen', (string) $user->email);

                    return [
                        'status' => 'CONFIRM_EMAIL',
                        'school_id' => $schoolId,
                        'email' => $normalizedEmail,
                        'user_id' => (int) $user->id,
                        'registration_source' => $registrationSource,
                        'requires_email_confirmation' => true,
                        'message' => 'Die Registrierung wartet bereits auf Ihre E-Mail-Bestaetigung. Wir haben einen neuen Code gesendet.',
                    ];
                }

                $this->activateRestaurantUser($user);

                return [
                    'status' => 'REGISTERED',
                    'school_id' => $schoolId,
                    'email' => $normalizedEmail,
                    'user_id' => (int) $user->id,
                    'registration_source' => $registrationSource,
                    'requires_email_confirmation' => false,
                    'message' => 'Das bestehende Benutzerkonto wurde fuer das Restaurant freigeschaltet.',
                ];
            }

            if ($registrationSource === 'existing_user') {
                $this->confirmRestaurantUser($user);

                return [
                    'status' => 'REGISTERED',
                    'school_id' => $schoolId,
                    'email' => $normalizedEmail,
                    'user_id' => (int) $user->id,
                    'registration_source' => $registrationSource,
                    'requires_email_confirmation' => false,
                    'message' => 'Das bestehende Benutzerkonto wurde fuer das Restaurant freigeschaltet.',
                ];
            }

            if ($this->newUsersMustConfirmEmail($schoolId)) {
                $this->preparePendingRestaurantUser($user);
                $this->userService->sendCode($user, 'E-Mail bestaetigen', (string) $user->email);

                return [
                    'status' => 'CONFIRM_EMAIL',
                    'school_id' => $schoolId,
                    'email' => $normalizedEmail,
                    'user_id' => (int) $user->id,
                    'registration_source' => $registrationSource,
                    'requires_email_confirmation' => true,
                    'message' => 'Die Registrierung wurde gespeichert. Bitte bestaetigen Sie Ihre E-Mail-Adresse mit dem zugesendeten Code.',
                ];
            }

            $this->activateRestaurantUser($user);

            return [
                'status' => 'REGISTERED',
                'school_id' => $schoolId,
                'email' => $normalizedEmail,
                'user_id' => (int) $user->id,
                'registration_source' => $registrationSource,
                'requires_email_confirmation' => false,
                'message' => 'Das Mittagskonto wurde erfolgreich registriert.',
            ];
        });
    }

    /**
     * @param  array{school_id:int|string, email:string, token_2fa:string}  $data
     * @return array<string, mixed>
     */
    public function confirmEmail(array $data): array
    {
        $schoolId = (int) $data['school_id'];
        $normalizedEmail = $this->normalizeEmail($data['email']);
        $token = trim((string) $data['token_2fa']);

        $payload = $this->manualRegistrationPayload($schoolId, $normalizedEmail);

        if (! $payload || ($payload['status'] ?? null) !== 'pending_email_confirmation') {
            abort(401, 'Der Code ist falsch oder abgelaufen.');
        }

        if (($payload['token_2fa'] ?? null) !== $token) {
            abort(401, 'Der Code ist falsch oder abgelaufen.');
        }

        $expiresAt = $payload['token_2fa_expires_at'] ?? null;
        if (! is_string($expiresAt) || now()->greaterThan(Carbon::parse($expiresAt))) {
            $this->forgetManualRegistration($schoolId, $normalizedEmail);
            abort(401, 'Der Code ist falsch oder abgelaufen.');
        }

        $confirmationToken = Str::random(40);

        $this->storeManualRegistrationPayload(
            $schoolId,
            $normalizedEmail,
            [
                'status' => 'email_confirmed',
                'token_2fa' => null,
                'token_2fa_expires_at' => null,
                'confirmation_token' => $confirmationToken,
                'confirmation_token_expires_at' => now()->addMinutes($this->manualRegistrationTtlMinutes())->toIso8601String(),
            ]
        );

        return [
            'status' => 'ENTER_USER_DATA',
            'school_id' => $schoolId,
            'email' => $normalizedEmail,
            'registration_source' => 'new_user',
            'confirmation_token' => $confirmationToken,
            'requires_email_confirmation' => false,
            'message' => 'Die E-Mail-Adresse wurde bestaetigt. Bitte geben Sie jetzt Nachname und Vorname ein.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRegistrationContext(int $schoolId, string $normalizedEmail): array
    {
        $existingUser = User::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->orderBy('id')
            ->first();

        if ($existingUser) {
            return [
                'registration_source' => 'existing_user',
                'user' => $existingUser,
            ];
        }

        $teacher = Teacher::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->orderBy('id')
            ->first();

        if ($teacher) {
            return [
                'registration_source' => 'teacher_list',
                'teacher' => $teacher,
            ];
        }

        $importStudent = Import116::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->orderBy('class')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->first();

        if ($importStudent) {
            return [
                'registration_source' => 'import116_student',
                'import_student' => $importStudent,
            ];
        }

        $parentImports = $this->parentImportsForEmail($schoolId, $normalizedEmail);
        if ($parentImports->isNotEmpty()) {
            return [
                'registration_source' => 'import116_parent',
                'parent_imports' => $parentImports,
            ];
        }

        return [
            'registration_source' => 'new_user',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function registrationContextPayload(array $context, ?string $normalizedEmail = null): array
    {
        return match ($context['registration_source']) {
            'existing_user' => [
                'existing_user' => $this->serializeMatchedUser($context['user']),
            ],
            'teacher_list' => [
                'teacher' => $this->serializeTeacher($context['teacher']),
            ],
            'import116_student' => [
                'student' => $this->serializeImportStudent($context['import_student']),
            ],
            'import116_parent' => [
                'parent_contact' => $this->serializeParentImports($context['parent_imports'], $normalizedEmail),
            ],
            default => [],
        };
    }

    private function registerExistingUser(array $context): User
    {
        /** @var User $user */
        $user = $context['user'];

        return $user;
    }

    private function registerTeacherUser(int $schoolId, string $normalizedEmail): User
    {
        $user = $this->teacherListService->createUserFromTeacher([
            'school_id' => $schoolId,
            'email' => $normalizedEmail,
        ]);

        return $user->fresh();
    }

    private function registerImportStudentUser(array $context): User
    {
        /** @var Import116 $importStudent */
        $importStudent = $context['import_student'];
        $result = $this->import116Service->createUserFromImport116($importStudent);

        return User::query()->findOrFail((int) $result['user_id']);
    }

    private function registerImportParentUser(
        int $schoolId,
        string $normalizedEmail,
        array $context,
        ?string $fallbackFirstName,
        ?string $fallbackLastName
    ): User {
        /** @var Collection<int, Import116> $parentImports */
        $parentImports = $context['parent_imports'];
        $nameParts = $this->parentNamePartsFromImports($parentImports, $normalizedEmail);
        $activeSchoolyearId = $this->activeSchoolyearIdForSchool($schoolId);

        return User::query()->create([
            'school_id' => $schoolId,
            'schoolyear_id' => $activeSchoolyearId,
            'first_name' => $nameParts['first_name'] ?? $fallbackFirstName,
            'last_name' => $nameParts['last_name'] ?? $fallbackLastName,
            'email' => $normalizedEmail,
            'password' => Hash::make(now()),
        ]);
    }

    private function registerManualUser(int $schoolId, string $normalizedEmail, ?string $firstName, ?string $lastName): User
    {
        return User::query()->create([
            'school_id' => $schoolId,
            'schoolyear_id' => $this->activeSchoolyearIdForSchool($schoolId),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $normalizedEmail,
            'password' => Hash::make(now()),
        ]);
    }

    private function completeVerifiedManualRestaurantRegistration(User $user): void
    {
        $user->email_verified_at = now();
        $user->confirmed_at = null;
        $user->restaurant_confirmed_at = null;
        $user->is_active = 1;
        $user->save();

        $this->assignPendingRestaurantRole($user);
    }

    private function activateRestaurantUser(User $user): void
    {
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->confirmed_at = $user->confirmed_at ?? now();
        $user->restaurant_confirmed_at = $user->restaurant_confirmed_at ?? now();
        $user->is_active = 1;
        $user->save();

        $this->assignConfirmedRestaurantRole($user);
    }

    private function confirmRestaurantUser(User $user): void
    {
        $user->restaurant_confirmed_at = $user->restaurant_confirmed_at ?? now();
        $user->is_active = 1;
        $user->save();

        $this->assignConfirmedRestaurantRole($user);
    }

    private function preparePendingRestaurantUser(User $user): void
    {
        $user->email_verified_at = null;
        $user->confirmed_at = null;
        $user->restaurant_confirmed_at = null;
        $user->is_active = 1;
        $user->save();

        $this->assignPendingRestaurantRole($user);
    }

    private function assignConfirmedRestaurantRole(User $user): void
    {
        if ($user->hasRole('lunch_candidate')) {
            $user->removeRole('lunch_candidate');
        }

        if (! $user->hasRole('lunch_user')) {
            $user->assignRole('lunch_user');
        }
    }

    private function assignPendingRestaurantRole(User $user): void
    {
        if ($user->hasRole('lunch_user')) {
            $user->removeRole('lunch_user');
        }

        if (! $user->hasRole('lunch_candidate')) {
            $user->assignRole('lunch_candidate');
        }
    }

    private function ensureManualNameInput(?string $firstName, ?string $lastName): void
    {
        $messages = [];

        if ($firstName === null) {
            $messages['data.first_name'] = 'Bitte geben Sie den Vornamen ein.';
        }

        if ($lastName === null) {
            $messages['data.last_name'] = 'Bitte geben Sie den Nachnamen ein.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function ensureManualRegistrationConfirmed(int $schoolId, string $normalizedEmail, string $confirmationToken): void
    {
        $payload = $this->manualRegistrationPayload($schoolId, $normalizedEmail);

        if (! $payload || ($payload['status'] ?? null) !== 'email_confirmed') {
            throw ValidationException::withMessages([
                'data.email' => 'Bitte bestaetigen Sie zuerst Ihre E-Mail-Adresse.',
            ]);
        }

        $payloadToken = $payload['confirmation_token'] ?? null;
        $expiresAt = $payload['confirmation_token_expires_at'] ?? null;

        if (! is_string($payloadToken) || ! hash_equals($payloadToken, $confirmationToken) || ! is_string($expiresAt) || now()->greaterThan(Carbon::parse($expiresAt))) {
            $this->forgetManualRegistration($schoolId, $normalizedEmail);

            throw ValidationException::withMessages([
                'data.confirmation_token' => 'Die E-Mail-Bestaetigung ist abgelaufen. Bitte fordern Sie einen neuen Code an.',
            ]);
        }
    }

    private function sendManualRegistrationCode(int $schoolId, string $normalizedEmail): void
    {
        $school = School::query()->findOrFail($schoolId);
        $token = (string) random_int(100000, 999999);

        $this->storeManualRegistrationPayload(
            $schoolId,
            $normalizedEmail,
            [
                'status' => 'pending_email_confirmation',
                'token_2fa' => $token,
                'token_2fa_expires_at' => now()->addMinutes($this->manualRegistrationTtlMinutes())->toIso8601String(),
                'confirmation_token' => null,
                'confirmation_token_expires_at' => null,
            ]
        );

        Notification::route('mail', $normalizedEmail)->notify(new StandardEmail([
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/'.$school->logo),
            'subject' => 'Code zur E-Mail-Bestaetigung',
            'markdown' => 'mails.homepage.sendCode',
            'token_2fa' => $token,
            'token-expire-time' => config('schooltool.token_expire_time'),
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function manualRegistrationPayload(int $schoolId, string $normalizedEmail): ?array
    {
        $payload = Cache::get($this->manualRegistrationCacheKey($schoolId, $normalizedEmail));

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeManualRegistrationPayload(int $schoolId, string $normalizedEmail, array $payload): void
    {
        Cache::put(
            $this->manualRegistrationCacheKey($schoolId, $normalizedEmail),
            $payload,
            now()->addMinutes($this->manualRegistrationTtlMinutes())
        );
    }

    private function forgetManualRegistration(int $schoolId, string $normalizedEmail): void
    {
        Cache::forget($this->manualRegistrationCacheKey($schoolId, $normalizedEmail));
    }

    private function manualRegistrationCacheKey(int $schoolId, string $normalizedEmail): string
    {
        return 'restaurant:manual-registration:'.$schoolId.':'.sha1($normalizedEmail);
    }

    private function manualRegistrationTtlMinutes(): int
    {
        return (int) config('schooltool.token_expire_time', 15);
    }

    private function activeSchoolyearIdForSchool(int $schoolId): ?int
    {
        $activeSchoolyearId = SchoolTool::query()
            ->where('school_id', $schoolId)
            ->value('active_schoolyear_id');

        return $activeSchoolyearId ? (int) $activeSchoolyearId : null;
    }

    private function newUsersMustConfirmEmail(int $schoolId): bool
    {
        return (bool) SchoolTool::query()
            ->where('school_id', $schoolId)
            ->value('restaurant_new_users_must_confirm_email');
    }

    /**
     * @return Collection<int, Import116>
     */
    private function parentImportsForEmail(int $schoolId, string $normalizedEmail): Collection
    {
        return Import116::query()
            ->where('school_id', $schoolId)
            ->where(function ($query) use ($normalizedEmail): void {
                $query->whereRaw('LOWER(mother_email) = ?', [$normalizedEmail])
                    ->orWhereRaw('LOWER(father_email) = ?', [$normalizedEmail]);
            })
            ->orderBy('class')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function matchedUsersFromParentEmail(int $schoolId, string $normalizedEmail): Collection
    {
        /** @var array<int, array{user: User, children: array<int, string>}> $matches */
        $matches = [];

        $this->parentImportsForEmail($schoolId, $normalizedEmail)
            ->each(function (Import116 $importRow) use ($schoolId, &$matches): void {
                $linkedUser = $this->linkedLunchUserForImportStudent($schoolId, $importRow);

                if (! $linkedUser) {
                    return;
                }

                $matches[$linkedUser->id] ??= [
                    'user' => $linkedUser,
                    'children' => [],
                ];

                $childLabel = trim(implode(' ', array_filter([
                    trim((string) $importRow->first_name),
                    trim((string) $importRow->last_name),
                ])));

                if ($childLabel !== '' && ! in_array($childLabel, $matches[$linkedUser->id]['children'], true)) {
                    $matches[$linkedUser->id]['children'][] = $childLabel;
                }
            });

        return collect($matches)
            ->map(function (array $match): User {
                $user = $match['user'];
                $user->setAttribute('matched_children', $match['children']);

                return $user;
            })
            ->values();
    }

    private function linkedLunchUserForImportStudent(int $schoolId, Import116 $import): ?User
    {
        $directUserId = (int) ($import->user_id ?? 0);
        if ($directUserId > 0) {
            $directUser = User::query()
                ->bySchoolAndRole($schoolId, 'lunch_user')
                ->where('id', $directUserId)
                ->first();

            if ($directUser) {
                return $directUser;
            }
        }

        $linkedByImportId = User::query()
            ->bySchoolAndRole($schoolId, 'lunch_user')
            ->where('import116_id', (int) $import->id)
            ->orderBy('id')
            ->first();

        if ($linkedByImportId) {
            return $linkedByImportId;
        }

        $studentEmail = trim((string) ($import->email ?? ''));
        if ($studentEmail === '') {
            return null;
        }

        return User::query()
            ->bySchoolAndRole($schoolId, 'lunch_user')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($studentEmail)])
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{id:int, name:string, email:string, schoolclass:?string, matched_children:array<int, string>}
     */
    private function serializeMatchedUser(User $user): array
    {
        $name = trim(implode(' ', array_filter([
            trim((string) $user->first_name),
            trim((string) $user->last_name),
        ])));

        return [
            'id' => (int) $user->id,
            'name' => $name !== '' ? $name : trim((string) $user->email),
            'email' => trim((string) $user->email),
            'schoolclass' => $user->schoolclass ? (string) $user->schoolclass : null,
            'matched_children' => collect($user->getAttribute('matched_children') ?? [])
                ->filter(fn (mixed $child): bool => is_string($child) && trim($child) !== '')
                ->map(fn (string $child): string => trim($child))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id:int, name:string, short:?string}
     */
    private function serializeTeacher(Teacher $teacher): array
    {
        return [
            'id' => (int) $teacher->id,
            'name' => trim(implode(' ', array_filter([
                trim((string) $teacher->first_name),
                trim((string) $teacher->last_name),
            ]))),
            'short' => $teacher->short ? (string) $teacher->short : null,
        ];
    }

    /**
     * @return array{id:int, name:string, schoolclass:string, email:?string}
     */
    private function serializeImportStudent(Import116 $importStudent): array
    {
        return [
            'id' => (int) $importStudent->id,
            'name' => trim(implode(' ', array_filter([
                trim((string) $importStudent->first_name),
                trim((string) $importStudent->last_name),
            ]))),
            'schoolclass' => trim((string) $importStudent->class),
            'email' => $this->normalizeNullableString($importStudent->email),
        ];
    }

    /**
     * @param  Collection<int, Import116>  $parentImports
     * @return array{name:?string, children:array<int, string>}
     */
    private function serializeParentImports(Collection $parentImports, ?string $normalizedEmail = null): array
    {
        return [
            'name' => $normalizedEmail ? $this->parentDisplayNameFromImports($parentImports, $normalizedEmail) : null,
            'children' => $parentImports
                ->map(fn (Import116 $import): string => trim(implode(' ', array_filter([
                    trim((string) $import->first_name),
                    trim((string) $import->last_name),
                ]))))
                ->filter(fn (string $child): bool => $child !== '')
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Import116>  $parentImports
     * @return array{first_name:?string, last_name:?string}
     */
    private function parentNamePartsFromImports(Collection $parentImports, string $normalizedEmail): array
    {
        $displayName = $this->parentDisplayNameFromImports($parentImports, $normalizedEmail);

        if ($displayName === null) {
            return [
                'first_name' => null,
                'last_name' => null,
            ];
        }

        $segments = preg_split('/\s+/u', $displayName) ?: [];
        $segments = array_values(array_filter($segments, fn (string $segment): bool => trim($segment) !== ''));

        if ($segments === []) {
            return [
                'first_name' => null,
                'last_name' => null,
            ];
        }

        if (count($segments) === 1) {
            return [
                'first_name' => null,
                'last_name' => $segments[0],
            ];
        }

        $lastName = array_pop($segments);

        return [
            'first_name' => trim(implode(' ', $segments)) ?: null,
            'last_name' => $lastName ?: null,
        ];
    }

    /**
     * @param  Collection<int, Import116>  $parentImports
     */
    private function parentDisplayNameFromImports(Collection $parentImports, string $normalizedEmail): ?string
    {
        $names = $parentImports
            ->map(function (Import116 $import) use ($normalizedEmail): ?string {
                if ($this->normalizeNullableString($import->mother_email) !== null
                    && mb_strtolower(trim((string) $import->mother_email)) === $normalizedEmail) {
                    return $this->normalizeNullableString($import->mother_name);
                }

                if ($this->normalizeNullableString($import->father_email) !== null
                    && mb_strtolower(trim((string) $import->father_email)) === $normalizedEmail) {
                    return $this->normalizeNullableString($import->father_name);
                }

                return null;
            })
            ->filter(fn (?string $name): bool => $name !== null)
            ->unique()
            ->values();

        return $names->count() === 1 ? $names->first() : null;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
