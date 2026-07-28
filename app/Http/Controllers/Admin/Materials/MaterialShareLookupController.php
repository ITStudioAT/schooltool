<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MaterialShareLookupController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $authUser = $this->materialsShareUser();

        $search = trim((string) $request->query('search', ''));
        if ($search === '') {
            return response()->json(['data' => []]);
        }

        $tokens = preg_split('/\s+/', $search) ?: [];

        $rows = User::query()
            ->where('school_id', (int) $authUser->school_id)
            ->whereKeyNot((int) $authUser->id)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', $this->materialsAccessRoleNames());
            })
            ->where(function ($query) use ($search, $tokens) {
                $like = '%'.$search.'%';
                $query
                    ->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('short', 'like', $like);

                foreach ($tokens as $token) {
                    $token = trim((string) $token);
                    if ($token === '') {
                        continue;
                    }

                    $query->orWhere('first_name', 'like', '%'.$token.'%')
                        ->orWhere('last_name', 'like', '%'.$token.'%')
                        ->orWhere('short', 'like', '%'.$token.'%');
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(25)
            ->get(['id', 'first_name', 'last_name', 'short', 'email']);

        return response()->json([
            'data' => $rows->map(function (User $user) {
                $fullName = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));

                return [
                    'id' => (int) $user->id,
                    'label' => $fullName !== '' ? $fullName : ($user->email ?: 'Benutzer'),
                    'first_name' => (string) ($user->first_name ?? ''),
                    'last_name' => (string) ($user->last_name ?? ''),
                    'short' => (string) ($user->short ?? ''),
                    'email' => (string) ($user->email ?? ''),
                ];
            })->values(),
        ]);
    }

    public function schools(): JsonResponse
    {
        $authUser = $this->materialsShareUser();

        $schools = School::query()
            ->where('id', '!=', (int) $authUser->school_id)
            ->orderBy('long_name')
            ->get(['id', 'long_name', 'short_name']);

        return response()->json([
            'data' => $schools->map(function (School $school) {
                $label = trim((string) ($school->long_name ?: $school->short_name ?: 'Schule'));

                return [
                    'id' => (int) $school->id,
                    'label' => $label,
                    'long_name' => (string) ($school->long_name ?? ''),
                    'short_name' => (string) ($school->short_name ?? ''),
                ];
            })->values(),
        ]);
    }

    public function externalUser(Request $request): JsonResponse
    {
        $authUser = $this->materialsShareUser();

        $data = $request->validate(
            [
                'target_school_id' => ['required', 'integer', 'min:1'],
                'user_email' => ['required', 'string', 'email'],
            ],
            [
                'target_school_id.required' => 'Bitte Schule wählen.',
                'target_school_id.integer' => 'Bitte Schule wählen.',
                'target_school_id.min' => 'Bitte Schule wählen.',
                'user_email.required' => 'Bitte E-Mail-Adresse eingeben.',
                'user_email.email' => 'Bitte eine gültige E-Mail-Adresse eingeben.',
            ]
        );

        $targetSchoolId = (int) $data['target_school_id'];
        $targetSchool = School::query()
            ->where('id', '!=', (int) $authUser->school_id)
            ->orderBy('long_name')
            ->find($targetSchoolId);

        if (! $targetSchool) {
            throw ValidationException::withMessages([
                'target_school_id' => ['Schule wurde nicht gefunden.'],
            ]);
        }

        $email = mb_strtolower(trim((string) $data['user_email']));
        $user = User::query()
            ->where('school_id', $targetSchoolId)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', $this->materialsAccessRoleNames());
            })
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first(['id', 'school_id', 'first_name', 'last_name', 'email']);

        if (! $user) {
            return response()->json([
                'data' => [
                    'exists' => false,
                ],
            ]);
        }

        $fullName = trim((string) (($user->last_name ?? '').' '.($user->first_name ?? '')));

        return response()->json([
            'data' => [
                'exists' => true,
                'id' => (int) $user->id,
                'school_id' => (int) $user->school_id,
                'school_label' => trim((string) ($targetSchool->long_name ?: $targetSchool->short_name ?: 'Schule')),
                'label' => $fullName !== '' ? $fullName : ((string) $user->email),
                'email' => (string) $user->email,
            ],
        ]);
    }

    private function materialsShareUser(): User
    {
        $authUser = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator']);
        if (! $authUser instanceof User) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }

    /**
     * @return array<int, string>
     */
    private function materialsAccessRoleNames(): array
    {
        return [
            'super_admin',
            'admin',
            'materials_admin',
            'materials_moderator',
        ];
    }
}
