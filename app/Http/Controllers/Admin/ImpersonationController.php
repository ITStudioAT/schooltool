<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaginateResource;
use App\Models\School;
use App\Models\User;
use App\Traits\HasRoleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lab404\Impersonate\Services\ImpersonateManager;

class ImpersonationController extends Controller
{
    use HasRoleTrait;

    public function status(Request $request, ImpersonateManager $manager)
    {
        if (! Auth::check()) {
            return response()->json([
                'is_impersonating' => false,
                'impersonator' => null,
                'current_user' => null,
            ], 200);
        }

        $isImpersonating = $manager->isImpersonating();
        $impersonator = null;
        $currentUser = Auth::user()?->loadMissing('selectedSchool:id,long_name,short_name');

        if ($isImpersonating) {
            $impersonatorId = $manager->getImpersonatorId();
            if ($impersonatorId) {
                $impersonator = User::query()
                    ->with('selectedSchool:id,long_name,short_name')
                    ->select(['id', 'school_id', 'last_name', 'first_name', 'email'])
                    ->find($impersonatorId);
            }
        }

        return response()->json([
            'is_impersonating' => $isImpersonating,
            'impersonator' => $impersonator ? [
                'id' => $impersonator->id,
                'last_name' => $impersonator->last_name,
                'first_name' => $impersonator->first_name,
                'email' => $impersonator->email,
                'school_id' => $impersonator->school_id,
                'school_name' => trim((string) ($impersonator->selectedSchool?->long_name ?: $impersonator->selectedSchool?->short_name)),
            ] : null,
            'current_user' => $currentUser ? [
                'id' => $currentUser->id,
                'last_name' => $currentUser->last_name,
                'first_name' => $currentUser->first_name,
                'email' => $currentUser->email,
                'school_id' => $currentUser->school_id,
                'school_name' => trim((string) ($currentUser->selectedSchool?->long_name ?: $currentUser->selectedSchool?->short_name)),
            ] : null,
        ], 200);
    }

    public function users(Request $request)
    {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'search_string' => ['nullable', 'string', 'max:255'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        $searchString = trim((string) ($validated['search_string'] ?? ''));
        $schoolId = isset($validated['school_id']) ? (int) $validated['school_id'] : null;
        $perPage = (int) ($validated['per_page'] ?? config('schooltool.pagination'));

        $users = User::query()
            ->with(['roles', 'selectedSchool:id,long_name,short_name'])
            ->where('id', '!=', $authUser->id)
            ->when($schoolId, fn($query) => $query->where('school_id', $schoolId))
            ->when($searchString !== '', function ($query) use ($searchString) {
                $query->where(function ($q) use ($searchString) {
                    $q->where('last_name', 'like', "%{$searchString}%")
                        ->orWhere('first_name', 'like', "%{$searchString}%")
                        ->orWhere('email', 'like', "%{$searchString}%")
                        ->orWhere('short', 'like', "%{$searchString}%")
                        ->orWhereHas('selectedSchool', function ($schoolQuery) use ($searchString) {
                            $schoolQuery->where('long_name', 'like', "%{$searchString}%")
                                ->orWhere('short_name', 'like', "%{$searchString}%");
                        });
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage);

        $users->setCollection(
            $users->getCollection()->map(function (User $user) {
                $fullName = trim((string) ($user->last_name ?? '') . ' ' . (string) ($user->first_name ?? ''));
                $schoolName = trim((string) ($user->selectedSchool?->long_name ?? $user->selectedSchool?->short_name ?? ''));
                $baseLabel = trim($fullName !== '' ? "{$fullName} ({$user->email})" : $user->email);
                $displayName = $schoolName !== '' ? "{$baseLabel} - {$schoolName}" : $baseLabel;

                return [
                    'id' => $user->id,
                    'last_name' => $user->last_name,
                    'first_name' => $user->first_name,
                    'email' => $user->email,
                    'school_id' => $user->school_id,
                    'school_name' => $schoolName,
                    'roles' => $user->roles->sortBy('name')->pluck('name')->values(),
                    'display_name' => $displayName,
                ];
            })
        );

        return response()->json([
            'data' => $users->items(),
            'meta' => new PaginateResource($users),
        ], 200);
    }

    public function schools(Request $request)
    {
        if (! $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $schools = School::query()
            ->select(['id', 'long_name', 'short_name'])
            ->orderBy('long_name')
            ->get()
            ->map(function (School $school) {
                return [
                    'id' => $school->id,
                    'long_name' => $school->long_name,
                    'short_name' => $school->short_name,
                    'display_name' => trim((string) ($school->long_name ?: $school->short_name)),
                ];
            })
            ->values();

        return response()->json([
            'data' => $schools,
        ], 200);
    }

    public function start(Request $request, ImpersonateManager $manager)
    {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($manager->isImpersonating()) {
            abort(409, 'Es läuft bereits eine Benutzer-Übernahme.');
        }

        $targetUser = User::findOrFail((int) $validated['user_id']);
        if ((int) $targetUser->id === (int) $authUser->id) {
            abort(422, 'Sie können sich nicht selbst übernehmen.');
        }

        if (! $authUser->canImpersonate()) {
            abort(403, 'Sie dürfen keine Benutzer übernehmen.');
        }
        if (! $targetUser->canBeImpersonated()) {
            abort(422, 'Dieser Benutzer kann nicht übernommen werden.');
        }

        if (! $manager->take($authUser, $targetUser, 'web')) {
            $manager->clear();
            abort(500, 'Benutzer-Übernahme konnte nicht gestartet werden.');
        }

        return response()->json([
            'message' => 'Benutzer-Übernahme gestartet.',
        ], 200);
    }

    public function stop(Request $request, ImpersonateManager $manager)
    {
        if (! Auth::check()) {
            abort(401, 'Nicht angemeldet.');
        }

        if (! $manager->isImpersonating()) {
            abort(409, 'Es läuft aktuell keine Benutzer-Übernahme.');
        }

        if (! $manager->leave()) {
            $manager->clear();
            abort(500, 'Benutzer-Übernahme konnte nicht beendet werden.');
        }

        return response()->json([
            'message' => 'Benutzer-Übernahme beendet.',
        ], 200);
    }
}
