<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Notifications\StandardEmail;
use App\Rules\Iban;
use App\Support\SafeHtml;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RestaurantSepaMandateService
{
    public function __construct(
        private RestaurantSepaMandatePdfService $pdfService,
        private SafeHtml $safeHtml,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function bootstrapFlow(User $user, string $entryPoint = 'login'): ?array
    {
        $flow = DB::transaction(function () use ($entryPoint, $user): ?array {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->requiresMandate($lockedUser)) {
                return null;
            }

            $mandate = $this->activeMandateForUser($lockedUser)
                ?? $this->createDraftMandate($lockedUser, $entryPoint);

            if (! $mandate->entry_point) {
                $mandate->entry_point = $entryPoint;
                $mandate->save();
            }

            return $this->serializeMandate($mandate);
        }, attempts: 3);

        if ($flow !== null) {
            session()->put(
                'restaurant.sepa_flow_bindings.'.(string) $flow['flow_uuid'],
                (int) $user->id,
            );
        }

        return $flow;
    }

    public function actorForFlow(?User $authenticatedUser, string $flowUuid): User
    {
        if ($authenticatedUser instanceof User) {
            return $authenticatedUser;
        }

        $boundUserId = session()->get('restaurant.sepa_flow_bindings.'.$flowUuid);

        if (! is_numeric($boundUserId)) {
            abort(401);
        }

        return User::query()->findOrFail((int) $boundUserId);
    }

    public function requiresMandate(User $user): bool
    {
        if ($user->sepa_at) {
            return false;
        }

        if (! $user->hasAnyRole(['lunch_user', 'lunch_candidate'])) {
            return false;
        }

        return $this->sepaOnlineEnabled($user->school_id);
    }

    /**
     * @param  array{
     *     flow_uuid:string,
     *     account_holder_name:string,
     *     address_line:string,
     *     postal_code:string,
     *     city:string,
     *     country:string,
     *     iban:string,
     *     bic:?string,
     *     child_entries:array<int, array{name:string, schoolclass:string}>
     * }  $data
     * @return array<string, mixed>
     */
    public function submitMandate(User $actor, array $data, string $ipAddress): array
    {
        $children = collect($data['child_entries'])
            ->map(fn (array $entry): array => [
                'name' => trim((string) ($entry['name'] ?? '')),
                'schoolclass' => trim((string) ($entry['schoolclass'] ?? '')),
            ])
            ->filter(fn (array $entry): bool => $entry['name'] !== '' || $entry['schoolclass'] !== '')
            ->values()
            ->all();

        if ($children === []) {
            throw ValidationException::withMessages([
                'data.child_entries' => 'Bitte geben Sie mindestens ein Kind an.',
            ]);
        }

        [$mandate, $confirmationCode] = DB::transaction(function () use ($actor, $children, $data, $ipAddress): array {
            $this->lockActor($actor);
            $mandate = $this->mandateByFlowUuid($actor, (string) $data['flow_uuid'], lockForUpdate: true);

            $this->requireState($mandate, 'draft', 'Das SEPA-Lastschriftmandat wurde bereits übermittelt.');

            $schoolSettings = $this->schoolSettingsForUser($mandate->user);
            $confirmationCode = $this->generateConfirmationCode();

            $mandate->fill([
                'status' => 'pending_code',
                'account_holder_name' => trim((string) $data['account_holder_name']),
                'address_line' => trim((string) $data['address_line']),
                'postal_code' => trim((string) $data['postal_code']),
                'city' => trim((string) $data['city']),
                'country' => trim((string) $data['country']),
                'iban' => $this->normalizeIban((string) $data['iban']),
                'bic' => $this->normalizeNullableString($data['bic'] ?? null),
                'child_entries' => $children,
                'sepa_payee_snapshot' => $schoolSettings['sepa_payee'],
                'sepa_mandate_text_snapshot' => $schoolSettings['sepa_mandate_text'],
                'accepted_at' => now(),
                'accepted_ip' => $ipAddress,
                'confirmation_code' => Hash::make($confirmationCode),
                'confirmation_code_expires_at' => now()->addMinutes($this->confirmationTtlMinutes()),
                'code_sent_at' => now(),
                'code_sent_ip' => $ipAddress,
            ]);
            $mandate->save();

            return [$mandate->fresh(['user.selectedSchool']), $confirmationCode];
        }, attempts: 3);

        $this->sendConfirmationCode($mandate, $confirmationCode);

        return [
            'status' => 'CODE_SENT',
            'message' => 'Wir haben einen 6-stelligen Bestätigungscode an Ihre E-Mail-Adresse gesendet.',
            'flow' => $this->serializeMandate($mandate),
        ];
    }

    /**
     * @param  array{flow_uuid:string, code:string}  $data
     * @return array<string, mixed>
     */
    public function confirmCode(User $actor, array $data, string $ipAddress): array
    {
        $mandate = DB::transaction(function () use ($actor, $data, $ipAddress): RestaurantSepaMandate {
            $lockedUser = $this->lockActor($actor);
            $mandate = $this->mandateByFlowUuid($actor, (string) $data['flow_uuid'], lockForUpdate: true);

            $this->requireState($mandate, 'pending_code', 'Der Bestätigungsschritt ist nicht mehr aktiv.');

            $expectedCodeHash = trim((string) ($mandate->confirmation_code ?? ''));
            $providedCode = trim((string) $data['code']);

            if ($expectedCodeHash === '' || ! Hash::check($providedCode, $expectedCodeHash)) {
                $this->throwInvalidConfirmationCode();
            }

            if (! $mandate->confirmation_code_expires_at || now()->greaterThan($mandate->confirmation_code_expires_at)) {
                $this->throwInvalidConfirmationCode();
            }

            $mandate->status = 'confirmed';
            $mandate->confirmed_at = now();
            $mandate->confirmed_ip = $ipAddress;
            $mandate->confirmation_code = null;
            $mandate->confirmation_code_expires_at = null;
            $mandate->save();

            $lockedUser->sepa_at = $lockedUser->sepa_at ?? now();
            $lockedUser->save();

            return $mandate->fresh(['user.selectedSchool']);
        }, attempts: 3);

        $this->sendConfirmedMandatePdf($mandate);

        return [
            'status' => 'CONFIRMED',
            'message' => 'Das SEPA-Lastschriftmandat wurde online bestätigt.',
            'flow' => $this->serializeMandate($mandate),
        ];
    }

    /**
     * @param  array{flow_uuid:string}  $data
     * @return array<string, mixed>
     */
    public function resendCode(User $actor, array $data, string $ipAddress): array
    {
        [$mandate, $confirmationCode] = DB::transaction(function () use ($actor, $data, $ipAddress): array {
            $this->lockActor($actor);
            $mandate = $this->mandateByFlowUuid($actor, (string) $data['flow_uuid'], lockForUpdate: true);

            $this->requireState(
                $mandate,
                'pending_code',
                'Der Bestätigungscode kann nur im Bestätigungsschritt erneut gesendet werden.',
            );

            $confirmationCode = $this->generateConfirmationCode();
            $mandate->fill([
                'confirmation_code' => Hash::make($confirmationCode),
                'confirmation_code_expires_at' => now()->addMinutes($this->confirmationTtlMinutes()),
                'code_sent_at' => now(),
                'code_sent_ip' => $ipAddress,
            ]);
            $mandate->save();

            return [$mandate->fresh(['user.selectedSchool']), $confirmationCode];
        }, attempts: 3);

        $this->sendConfirmationCode($mandate, $confirmationCode);

        return [
            'status' => 'CODE_SENT',
            'message' => 'Wir haben einen neuen 6-stelligen Bestätigungscode an Ihre E-Mail-Adresse gesendet.',
            'flow' => $this->serializeMandate($mandate),
        ];
    }

    /**
     * @param  array{flow_uuid:string}  $data
     * @return array<string, mixed>
     */
    public function completeFlow(User $actor, array $data, string $ipAddress): array
    {
        $mandate = DB::transaction(function () use ($actor, $data, $ipAddress): RestaurantSepaMandate {
            $this->lockActor($actor);
            $mandate = $this->mandateByFlowUuid($actor, (string) $data['flow_uuid'], lockForUpdate: true);

            $this->requireState($mandate, 'confirmed', 'Das SEPA-Lastschriftmandat ist nicht abschließbar.');

            if (! $mandate->confirmed_at) {
                throw ValidationException::withMessages([
                    'data.flow_uuid' => 'Das SEPA-Lastschriftmandat muss zuerst bestätigt werden.',
                ]);
            }

            $mandate->status = 'completed';
            $mandate->completed_at = now();
            $mandate->completed_ip = $ipAddress;
            $mandate->save();

            return $mandate->fresh(['user.selectedSchool', 'user.roles']);
        }, attempts: 3);

        $user = $mandate->user;
        $loggedIn = false;

        session()->forget('restaurant.sepa_flow_bindings.'.$mandate->flow_uuid);

        if ($user->hasRole('lunch_user')) {
            $user->rememberLogin();
            Auth::guard('web')->login($user, true);
            session()->regenerate();
            $loggedIn = true;
        }

        return [
            'status' => 'COMPLETED',
            'logged_in' => $loggedIn,
            'pending_confirmation' => $user->hasRole('lunch_candidate') && ! $user->hasRole('lunch_user'),
            'message' => $loggedIn
                ? 'Das SEPA-Lastschriftmandat wurde gespeichert. Sie sind jetzt angemeldet.'
                : 'Das SEPA-Lastschriftmandat wurde gespeichert. Die Freischaltung für das Restaurant ist noch ausständig.',
            'flow' => $this->serializeMandate($mandate),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicSettingsForSchool(?School $school): array
    {
        $schoolTool = $school?->schoolTool;

        return [
            'sepa_online_enabled' => (bool) ($schoolTool?->restaurant_sepa_online_enabled ?? false),
            'sepa_payee' => $this->safeHtml->sanitize((string) ($schoolTool?->restaurant_sepa_payee ?? '')),
            'sepa_mandate_text' => $this->safeHtml->sanitize((string) ($schoolTool?->restaurant_sepa_mandate_text ?? '')),
        ];
    }

    private function sepaOnlineEnabled(int $schoolId): bool
    {
        return (bool) SchoolTool::query()
            ->where('school_id', $schoolId)
            ->value('restaurant_sepa_online_enabled');
    }

    private function activeMandateForUser(User $user): ?RestaurantSepaMandate
    {
        return RestaurantSepaMandate::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    private function createDraftMandate(User $user, string $entryPoint): RestaurantSepaMandate
    {
        $schoolSettings = $this->schoolSettingsForUser($user);

        return RestaurantSepaMandate::query()->create([
            'user_id' => $user->id,
            'school_id' => $user->school_id,
            'flow_uuid' => (string) Str::uuid(),
            'status' => 'draft',
            'entry_point' => $entryPoint,
            'child_entries' => $this->defaultChildEntries($user),
            'sepa_payee_snapshot' => $schoolSettings['sepa_payee'],
            'sepa_mandate_text_snapshot' => $schoolSettings['sepa_mandate_text'],
        ]);
    }

    /**
     * @return array<int, array{name:string, schoolclass:string}>
     */
    private function defaultChildEntries(User $user): array
    {
        $email = mb_strtolower(trim((string) $user->email));

        $children = Import116::query()
            ->where('school_id', $user->school_id)
            ->where(function ($query) use ($email): void {
                $query->whereRaw('LOWER(TRIM(mother_email)) = ?', [$email])
                    ->orWhereRaw('LOWER(TRIM(father_email)) = ?', [$email]);
            })
            ->orderBy('class')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['first_name', 'last_name', 'class'])
            ->map(fn (Import116 $child): array => [
                'name' => trim(implode(' ', array_filter([
                    trim((string) $child->first_name),
                    trim((string) $child->last_name),
                ]))),
                'schoolclass' => trim((string) ($child->class ?? '')),
            ])
            ->filter(fn (array $entry): bool => $entry['name'] !== '' || $entry['schoolclass'] !== '')
            ->values()
            ->all();

        if ($children !== []) {
            return $children;
        }

        $fallbackName = trim(implode(' ', array_filter([
            trim((string) $user->first_name),
            trim((string) $user->last_name),
        ])));

        return [[
            'name' => $fallbackName,
            'schoolclass' => trim((string) ($user->schoolclass ?? '')),
        ]];
    }

    /**
     * @return array{sepa_payee:string, sepa_mandate_text:string}
     */
    private function schoolSettingsForUser(User $user): array
    {
        $schoolTool = SchoolTool::query()
            ->where('school_id', $user->school_id)
            ->first();

        return [
            'sepa_payee' => $this->safeHtml->sanitize((string) ($schoolTool?->restaurant_sepa_payee ?? '')),
            'sepa_mandate_text' => $this->safeHtml->sanitize((string) ($schoolTool?->restaurant_sepa_mandate_text ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMandate(RestaurantSepaMandate $mandate): array
    {
        $mandate->loadMissing('user.selectedSchool');
        $user = $mandate->user;
        $school = $user?->selectedSchool;
        $settings = $this->schoolSettingsForUser($user);
        $children = collect($mandate->child_entries ?: $this->defaultChildEntries($user))
            ->map(fn (array $entry): array => [
                'name' => trim((string) ($entry['name'] ?? '')),
                'schoolclass' => trim((string) ($entry['schoolclass'] ?? '')),
            ])
            ->values()
            ->all();

        return [
            'flow_uuid' => $mandate->flow_uuid,
            'status' => $mandate->status,
            'entry_point' => $mandate->entry_point,
            'school_name' => $school?->long_name ?: $school?->short_name,
            'email' => trim((string) ($user?->email ?? '')),
            'account_holder_name' => trim((string) ($mandate->account_holder_name ?? '')),
            'address_line' => trim((string) ($mandate->address_line ?? '')),
            'postal_code' => trim((string) ($mandate->postal_code ?? '')),
            'city' => trim((string) ($mandate->city ?? '')),
            'country' => trim((string) ($mandate->country ?? 'Österreich')) ?: 'Österreich',
            'iban' => $this->redactIban((string) ($mandate->iban ?? '')),
            'bic' => $this->redactBic((string) ($mandate->bic ?? '')),
            'child_entries' => $children,
            'sepa_payee' => $this->safeHtml->sanitize(
                (string) ($mandate->sepa_payee_snapshot ?: $settings['sepa_payee']),
            ),
            'sepa_mandate_text' => $this->safeHtml->sanitize(
                (string) ($mandate->sepa_mandate_text_snapshot ?: $settings['sepa_mandate_text']),
            ),
            'accepted_at' => $mandate->accepted_at?->toIso8601String(),
            'confirmed_at' => $mandate->confirmed_at?->toIso8601String(),
            'completed_at' => $mandate->completed_at?->toIso8601String(),
            'signature_uuid' => $mandate->confirmed_at ? $mandate->flow_uuid : null,
        ];
    }

    private function mandateByFlowUuid(
        User $actor,
        string $flowUuid,
        bool $lockForUpdate = false,
    ): RestaurantSepaMandate {
        $query = RestaurantSepaMandate::query()
            ->with('user.selectedSchool')
            ->where('user_id', $actor->id)
            ->where('flow_uuid', $flowUuid);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function lockActor(User $actor): User
    {
        return User::query()
            ->whereKey($actor->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function requireState(RestaurantSepaMandate $mandate, string $expectedState, string $message): void
    {
        if ($mandate->status === $expectedState) {
            return;
        }

        throw ValidationException::withMessages([
            'data.flow_uuid' => $message,
        ]);
    }

    private function throwInvalidConfirmationCode(): never
    {
        throw ValidationException::withMessages([
            'data.code' => 'Der Code ist falsch oder abgelaufen.',
        ]);
    }

    private function generateConfirmationCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function confirmationTtlMinutes(): int
    {
        return (int) config('schooltool.token_expire_time', 15);
    }

    private function sendConfirmationCode(RestaurantSepaMandate $mandate, string $confirmationCode): void
    {
        $user = $mandate->user;
        $school = $user->selectedSchool ?: School::query()->find($user->school_id);

        Notification::route('mail', EmailAliasResolver::resolveConfigured((string) $user->email))
            ->notify(new StandardEmail([
                'from_address' => config('schooltool.noreply_email'),
                'from_name' => $school?->long_name ?: config('app.name'),
                'logo' => $school?->logo ? asset('/storage/images/'.$school->logo) : null,
                'subject' => 'Code zur SEPA-Bestätigung',
                'markdown' => 'mails.homepage.sendCode',
                'token_2fa' => $confirmationCode,
                'token-expire-time' => $this->confirmationTtlMinutes(),
            ]));
    }

    private function sendConfirmedMandatePdf(RestaurantSepaMandate $mandate): void
    {
        $mandate->loadMissing(['user.selectedSchool']);

        $recipientEmails = collect([
            trim((string) ($mandate->user?->email ?? '')),
            trim((string) $this->restaurantServiceEmailForSchoolId((int) $mandate->school_id)),
        ])
            ->filter()
            ->unique()
            ->values();

        if ($recipientEmails->isEmpty()) {
            return;
        }

        $attachment = [
            'data' => $this->pdfService->createPdfContent($mandate),
            'name' => "sepa_lastschriftmandat_{$mandate->flow_uuid}.pdf",
            'options' => ['mime' => 'application/pdf'],
        ];
        $mailData = $this->confirmedSepaMandateMailData($mandate);

        $recipientEmails->each(function (string $email) use ($attachment, $mailData): void {
            Notification::route('mail', EmailAliasResolver::resolveConfigured($email))
                ->notify(new StandardEmail($mailData, $attachment));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function confirmedSepaMandateMailData(RestaurantSepaMandate $mandate): array
    {
        $school = $mandate->user?->selectedSchool ?: School::query()->find($mandate->school_id);

        return [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school?->long_name ?: config('app.name'),
            'logo' => $school?->logo ? asset('/storage/images/'.$school->logo) : null,
            'subject' => 'SEPA-Lastschriftmandat als PDF',
            'markdown' => 'spa::mails.homepage.sendSepaMandate',
        ];
    }

    private function restaurantServiceEmailForSchoolId(int $schoolId): string
    {
        return trim((string) SchoolTool::query()
            ->where('school_id', $schoolId)
            ->value('restaurant_service_email'));
    }

    private function normalizeIban(string $value): string
    {
        return Iban::validateIban($value)['normalizedIban'];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function redactIban(string $iban): string
    {
        $normalized = $this->normalizeNullableString($iban);

        if ($normalized === null) {
            return '';
        }

        return Str::mask($normalized, '*', 4, max(0, strlen($normalized) - 8));
    }

    private function redactBic(string $bic): string
    {
        $normalized = $this->normalizeNullableString($bic);

        if ($normalized === null) {
            return '';
        }

        return Str::mask($normalized, '*', 0, max(0, strlen($normalized) - 4));
    }
}
