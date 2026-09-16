<?php

namespace App\Services;

use App\Models\FeaturePreviewSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class FeaturePreviewService
{
    public function isPreview(): bool
    {
        return (bool) config('schooltool.preview.instance', false);
    }

    public function schemaReady(): bool
    {
        try {
            return Schema::hasTable('feature_preview_settings')
                && Schema::hasColumn('users', 'feature_preview_allowed');
        } catch (QueryException) {
            return false;
        }
    }

    public function enabled(): bool
    {
        return $this->schemaReady()
            && (bool) FeaturePreviewSetting::query()->whereKey(1)->value('enabled');
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

        if ($user->confirmed_at === null) {
            return 'Benutzerkonto noch nicht bestätigt.';
        }

        if (! $user->hasAdminShellAccess()) {
            return 'Keine Berechtigung für den Admin-Bereich.';
        }

        return null;
    }

    public function allowed(?User $user): bool
    {
        if (! $user || ! $this->schemaReady()) {
            return false;
        }

        $currentUser = User::query()->with('roles')->find($user->getKey());

        return $currentUser !== null
            && (bool) $currentUser->feature_preview_allowed
            && $this->eligible($currentUser);
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

        return [
            'is_preview' => $this->isPreview(),
            'enabled' => $enabled,
            'can_access' => $enabled && $allowed,
            'url' => $maySeeUrl ? $this->adminUrl('url') : null,
            'live_url' => $this->isPreview() ? $this->adminUrl('live_url') : null,
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

    /** @return array{enabled: bool, users: array<int, array<string, mixed>>} */
    public function managementState(User $actor): array
    {
        abort_unless($this->schemaReady(), 503, 'Die Vorschauverwaltung ist noch nicht eingerichtet.');

        $roles = app(AccessScopeService::class)->roleNamesForScope('admin_shell_access');
        $roles[] = 'super_admin';
        $users = User::query()
            ->with(['roles', 'selectedSchool:id,long_name,short_name'])
            ->where('school_id', $actor->school_id)
            ->where(function ($query) use ($roles): void {
                $query->where('feature_preview_allowed', true)
                    ->orWhereHas('roles', function ($query) use ($roles): void {
                        $query->where('is_admin', true)->orWhereIn('name', $roles);
                    });
            })
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
