<?php

namespace App\Services;

use App\Models\FeaturePreviewSetting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FeaturePreviewService
{
    public static function authenticationFingerprint(User $user): string
    {
        return self::authenticationFingerprintFromAttributes($user->getRawOriginal());
    }

    /** @param array<string, mixed> $attributes */
    public static function authenticationFingerprintFromAttributes(array $attributes): string
    {
        $identity = [
            'id' => (int) ($attributes['id'] ?? 0),
            'school_id' => (int) ($attributes['school_id'] ?? 0),
            'email' => mb_strtolower(trim((string) ($attributes['email'] ?? ''))),
            'created_at' => (string) ($attributes['created_at'] ?? ''),
        ];
        foreach (['password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'email_2fa_verified_at', 'confirmed_at', 'email_verified_at'] as $attribute) {
            $identity[$attribute] = (string) ($attributes[$attribute] ?? '');
        }
        $identity['is_2fa'] = (bool) ($attributes['is_2fa'] ?? false);
        $identity['email_2fa'] = mb_strtolower(trim((string) ($attributes['email_2fa'] ?? '')));

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }

    public function isPreview(): bool
    {
        return (bool) config('schooltool.preview.instance', false);
    }

    public function schemaReady(): bool
    {
        try {
            if ($this->isPreview()) {
                return (app(FeaturePreviewControlClient::class)->request('status')['schema_ready'] ?? null) === true;
            }

            return Schema::hasTable('feature_preview_settings')
                && Schema::hasColumn('users', 'feature_preview_allowed');
        } catch (Throwable) {
            return false;
        }
    }

    public function enabled(): bool
    {
        try {
            if ($this->isPreview()) {
                return (app(FeaturePreviewControlClient::class)->request('status')['enabled'] ?? null) === true;
            }

            return $this->schemaReady()
                && (bool) FeaturePreviewSetting::query()->whereKey(1)->value('enabled');
        } catch (Throwable) {
            return false;
        }
    }

    public function eligible(User $user): bool
    {
        return $this->ineligibleReason($user) === null;
    }

    private function ineligibleReason(User $user): ?string
    {
        if (! $user->is_active) {
            return 'Benutzerkonto ist deaktiviert.';
        }

        if ($user->confirmed_at === null && ($user->hasAdminShellAccess() || $user->email_verified_at === null)) {
            return 'Benutzerkonto noch nicht bestätigt.';
        }

        return null;
    }

    public function allowed(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (! $this->isPreview()) {
            if (! $this->schemaReady()) {
                return false;
            }

            $currentUser = User::query()->with('roles')->find($user->getKey());

            return $currentUser !== null
                && (bool) $currentUser->feature_preview_allowed
                && $this->eligible($currentUser);
        }

        try {
            $payload = $this->admissionPayload($user);

            return $payload !== null
                && (app(FeaturePreviewControlClient::class)->request('admission', $payload)['allowed'] ?? null) === true;
        } catch (Throwable) {
            return false;
        }
    }

    public function authenticationRecipientAllowed(User $localUser, string $recipient, string $recipientKind, ?int $schoolyearId = null): bool
    {
        if (! $this->isPreview()) {
            return true;
        }

        try {
            $payload = $this->admissionPayload($localUser);
            if ($payload === null) {
                return false;
            }

            $payload['recipient'] = mb_strtolower(trim($recipient));
            $payload['recipient_kind'] = $recipientKind;
            $payload['schoolyear_id'] = $schoolyearId;

            return (app(FeaturePreviewControlClient::class)->request('recipient', $payload)['allowed'] ?? null) === true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    private function admissionPayload(User $user): ?array
    {
        $current = User::query()->with(['roles.permissions', 'permissions'])->find($user->getKey());
        $snapshot = app(FeaturePreviewSnapshotIdentityStore::class)->read();
        $baseline = $snapshot['users'][(string) $user->getKey()] ?? null;
        $source = $snapshot['source_identity'] ?? null;
        if (! $current || ! $this->eligible($current) || ! is_string($baseline)
            || preg_match('/\A[a-f0-9]{64}\z/', $baseline) !== 1 || ! is_array($source) || $current->created_at === null) {
            return null;
        }

        return [
            'source_identity' => $source,
            'identity' => [
                'id' => (int) $current->id,
                'school_id' => (int) $current->school_id,
                'email' => mb_strtolower(trim((string) $current->email)),
                'created_at' => (string) $current->getRawOriginal('created_at'),
            ],
            'auth_fingerprint' => $baseline,
            'roles' => $current->roles->map(fn ($role): array => [
                'name' => $role->name, 'guard_name' => $role->guard_name, 'is_admin' => (bool) $role->getAttribute('is_admin'),
            ])->values()->all(),
            'permissions' => $current->getAllPermissions()->map(fn ($permission): array => [
                'name' => $permission->name, 'guard_name' => $permission->guard_name,
            ])->values()->all(),
        ];
    }

    public function assertCanEnter(?User $user): void
    {
        if ($this->isPreview()) {
            abort_unless($this->enabled() && $this->allowed($user), 403, 'Für dieses Konto ist die Vorschau nicht freigegeben.');
        }
    }

    /** @return array{is_preview: bool, enabled: bool, can_access: bool, url: ?string, live_url: ?string} */
    public function context(?User $user): array
    {
        $enabled = $this->enabled();
        $allowed = $this->allowed($user);
        $maySeeUrl = $allowed || $user?->hasRole('super_admin');
        $usesAdminArea = $user?->hasAdminShellAccess() ?? true;

        return [
            'is_preview' => $this->isPreview(),
            'enabled' => $enabled,
            'can_access' => $enabled && $allowed,
            'url' => $maySeeUrl ? ($usesAdminArea ? $this->adminUrl('url') : $this->homepageUrl('url')) : null,
            'live_url' => $this->isPreview() ? ($usesAdminArea ? $this->adminUrl('live_url') : $this->homepageUrl('live_url')) : null,
        ];
    }

    public function adminUrl(string $key): ?string
    {
        $url = (string) config("schooltool.preview.{$key}", '');
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        return rtrim($url, '/').(str_ends_with(rtrim($url, '/'), '/admin') ? '' : '/admin');
    }

    public function homepageUrl(string $key): ?string
    {
        $adminUrl = $this->adminUrl($key);

        return $adminUrl === null ? null : substr($adminUrl, 0, -6).'/';
    }

    /** @return array{enabled: bool, users: array<int, array<string, mixed>>} */
    public function managementState(User $actor): array
    {
        abort_if($this->isPreview(), 403, 'Die Vorschau wird ausschließlich in der Hauptanwendung verwaltet.');
        abort_unless($this->schemaReady(), 503, 'Die Vorschauverwaltung ist noch nicht eingerichtet.');

        $users = User::query()
            ->with(['roles', 'selectedSchool:id,long_name,short_name'])
            ->where('school_id', $actor->school_id)
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')
            ->get();

        return [
            'enabled' => $this->enabled(),
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'allowed' => (bool) $user->feature_preview_allowed,
                'eligible' => $this->eligible($user),
                'ineligible_reason' => $this->ineligibleReason($user),
                'roles' => $user->roles->pluck('name')->values()->all(),
                'school_name' => $user->selectedSchool?->long_name ?: $user->selectedSchool?->short_name,
            ])->all(),
        ];
    }
}
