<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RestaurantSepaMandateService
{
    /**
     * @return array<string, mixed>|null
     */
    public function bootstrapFlow(User $user, string $entryPoint = 'login'): ?array
    {
        if (! $this->requiresMandate($user)) {
            return null;
        }

        $mandate = $this->activeMandateForUser($user) ?? $this->createDraftMandate($user, $entryPoint);

        if (! $mandate->entry_point) {
            $mandate->entry_point = $entryPoint;
            $mandate->save();
        }

        return $this->serializeMandate($mandate);
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
     *     iban:string,
     *     bic:?string,
     *     child_entries:array<int, array{name:string, schoolclass:string}>
     * }  $data
     * @return array<string, mixed>
     */
    public function submitMandate(array $data, string $ipAddress): array
    {
        $mandate = $this->mandateByFlowUuid((string) $data['flow_uuid']);

        if ($mandate->completed_at) {
            return $this->serializeMandate($mandate);
        }

        $children = collect($data['child_entries'])
            ->map(function (array $entry): array {
                return [
                    'name' => trim((string) ($entry['name'] ?? '')),
                    'schoolclass' => trim((string) ($entry['schoolclass'] ?? '')),
                ];
            })
            ->filter(fn (array $entry): bool => $entry['name'] !== '' || $entry['schoolclass'] !== '')
            ->values()
            ->all();

        if ($children === []) {
            throw ValidationException::withMessages([
                'data.child_entries' => 'Bitte geben Sie mindestens ein Kind an.',
            ]);
        }

        $schoolSettings = $this->schoolSettingsForUser($mandate->user);
        $confirmationCode = $this->generateConfirmationCode();

        $mandate->fill([
            'status' => 'pending_code',
            'account_holder_name' => trim((string) $data['account_holder_name']),
            'address_line' => trim((string) $data['address_line']),
            'iban' => $this->normalizeIban((string) $data['iban']),
            'bic' => $this->normalizeNullableString($data['bic'] ?? null),
            'child_entries' => $children,
            'sepa_payee_snapshot' => $schoolSettings['sepa_payee'],
            'sepa_mandate_text_snapshot' => $schoolSettings['sepa_mandate_text'],
            'accepted_at' => now(),
            'accepted_ip' => $ipAddress,
            'confirmation_code' => $confirmationCode,
            'confirmation_code_expires_at' => now()->addMinutes($this->confirmationTtlMinutes()),
            'code_sent_at' => now(),
            'code_sent_ip' => $ipAddress,
        ]);
        $mandate->save();

        $this->sendConfirmationCode($mandate, $confirmationCode);

        return [
            'status' => 'CODE_SENT',
            'message' => 'Wir haben einen 6-stelligen Bestätigungscode an Ihre E-Mail-Adresse gesendet.',
            'flow' => $this->serializeMandate($mandate->fresh()),
        ];
    }

    /**
     * @param  array{flow_uuid:string, code:string}  $data
     * @return array<string, mixed>
     */
    public function confirmCode(array $data, string $ipAddress): array
    {
        $mandate = $this->mandateByFlowUuid((string) $data['flow_uuid']);

        if ($mandate->confirmed_at) {
            return [
                'status' => 'CONFIRMED',
                'message' => 'Das SEPA-Lastschriftmandat wurde bereits bestätigt.',
                'flow' => $this->serializeMandate($mandate),
            ];
        }

        $expectedCode = trim((string) ($mandate->confirmation_code ?? ''));
        $providedCode = trim((string) $data['code']);

        if ($expectedCode === '' || $providedCode !== $expectedCode) {
            throw ValidationException::withMessages([
                'data.code' => 'Der Code ist falsch oder abgelaufen.',
            ]);
        }

        if (! $mandate->confirmation_code_expires_at || now()->greaterThan($mandate->confirmation_code_expires_at)) {
            throw ValidationException::withMessages([
                'data.code' => 'Der Code ist falsch oder abgelaufen.',
            ]);
        }

        DB::transaction(function () use ($mandate, $ipAddress): void {
            $mandate->status = 'confirmed';
            $mandate->confirmed_at = now();
            $mandate->confirmed_ip = $ipAddress;
            $mandate->confirmation_code = null;
            $mandate->confirmation_code_expires_at = null;
            $mandate->save();

            $user = $mandate->user()->lockForUpdate()->firstOrFail();
            $user->sepa_at = $user->sepa_at ?? now();
            $user->save();
        });

        return [
            'status' => 'CONFIRMED',
            'message' => 'Das SEPA-Lastschriftmandat wurde online bestätigt.',
            'flow' => $this->serializeMandate($mandate->fresh()),
        ];
    }

    /**
     * @param  array{flow_uuid:string}  $data
     * @return array<string, mixed>
     */
    public function completeFlow(array $data, string $ipAddress): array
    {
        $mandate = $this->mandateByFlowUuid((string) $data['flow_uuid']);

        if (! $mandate->confirmed_at) {
            throw ValidationException::withMessages([
                'data.flow_uuid' => 'Das SEPA-Lastschriftmandat muss zuerst bestätigt werden.',
            ]);
        }

        $mandate->status = 'completed';
        $mandate->completed_at = $mandate->completed_at ?? now();
        $mandate->completed_ip = $mandate->completed_ip ?? $ipAddress;
        $mandate->save();

        $user = $mandate->user()->firstOrFail();
        $loggedIn = false;

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
            'flow' => $this->serializeMandate($mandate->fresh()),
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
            'sepa_payee' => trim((string) ($schoolTool?->restaurant_sepa_payee ?? '')),
            'sepa_mandate_text' => trim((string) ($schoolTool?->restaurant_sepa_mandate_text ?? '')),
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
            ->map(function (Import116 $child): array {
                return [
                    'name' => trim(implode(' ', array_filter([
                        trim((string) $child->first_name),
                        trim((string) $child->last_name),
                    ]))),
                    'schoolclass' => trim((string) ($child->class ?? '')),
                ];
            })
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
            'sepa_payee' => trim((string) ($schoolTool?->restaurant_sepa_payee ?? '')),
            'sepa_mandate_text' => trim((string) ($schoolTool?->restaurant_sepa_mandate_text ?? '')),
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
            'iban' => trim((string) ($mandate->iban ?? '')),
            'bic' => trim((string) ($mandate->bic ?? '')),
            'child_entries' => $children,
            'sepa_payee' => $mandate->sepa_payee_snapshot ?: $settings['sepa_payee'],
            'sepa_mandate_text' => $mandate->sepa_mandate_text_snapshot ?: $settings['sepa_mandate_text'],
            'accepted_at' => $mandate->accepted_at?->toIso8601String(),
            'confirmed_at' => $mandate->confirmed_at?->toIso8601String(),
            'completed_at' => $mandate->completed_at?->toIso8601String(),
            'signature_uuid' => $mandate->confirmed_at ? $mandate->flow_uuid : null,
        ];
    }

    private function mandateByFlowUuid(string $flowUuid): RestaurantSepaMandate
    {
        return RestaurantSepaMandate::query()
            ->with('user.selectedSchool')
            ->where('flow_uuid', $flowUuid)
            ->firstOrFail();
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

        Notification::route('mail', (string) $user->email)->notify(new StandardEmail([
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school?->long_name ?: config('app.name'),
            'logo' => $school?->logo ? asset('/storage/images/'.$school->logo) : null,
            'subject' => 'Code zur SEPA-Bestätigung',
            'markdown' => 'mails.homepage.sendCode',
            'token_2fa' => $confirmationCode,
            'token-expire-time' => $this->confirmationTtlMinutes(),
        ]));
    }

    private function normalizeIban(string $value): string
    {
        return mb_strtoupper(str_replace(' ', '', trim($value)));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }
}
