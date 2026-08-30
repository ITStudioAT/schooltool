<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexUserWithRoleRequest;
use App\Http\Requests\Admin\SavePasswordRequest;
use App\Http\Requests\Admin\SavePasswordWithCodeRequest;
use App\Http\Requests\Admin\SaveUserRoleRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UpdateUserWithCodeRequest;
use App\Http\Resources\Admin\RoleResource;
use App\Http\Resources\Admin\UserWithRoleResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminService;
use App\Traits\PaginationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserWithRoleController extends Controller
{
    use PaginationTrait;

    public function roles(Request $request)
    {

        if (! $auth_user = $this->userHasRole(['admin'])) {
            return response()->json(['roles' => []], 200);
            abort(403, 'Sie haben keine Berechtigung');
        }

        $roles = Role::whereNotIn('name', ['super_admin'])->orderBy('name')->get();
        $roleResource = RoleResource::collection($roles);
        $data = ['roles' => $roleResource];

        $roles = $roleResource->resolve();

        return response()->json($data, 200);
    }

    public function saveUserRoles(SaveUserRoleRequest $request)
    {

        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $user = User::query()
            ->where('school_id', $auth_user->school_id)
            ->findOrFail($validated['id']);
        if ($user->hasRole('super_admin')) {
            $validated['roles'][] = 'super_admin';
        }

        $user->syncRoles($validated['roles']);
    }

    public function index(IndexUserWithRoleRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $search_model = $validated['search_model'] ?? [];
        $query = User::with('roles')
            ->where('school_id', $auth_user->school_id)
            ->orderBy('last_name')
            ->orderBy('first_name');

        // search_string (optional)
        if (! empty($search_model['search_string'])) {
            $search = $search_model['search_string'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // is_active
        if (isset($search_model['role'])) {
            $query->role($search_model['role']);
        }

        $pagination = UserWithRoleResource::collection($query->paginate(config('spa.pagination')));

        return response()->json($this->makePagination($pagination), 200);
    }

    public function store(StoreUserRequest $request)
    {

        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $validated = $this->convertConfirmedVerified($validated);
        $validated['school_id'] = $auth_user->school_id;
        $validated['password'] = Hash::make(now());

        // Extract protected fields before create since they're not fillable
        $is_active = $validated['is_active'] ?? false;
        $confirmed_at = $validated['confirmed_at'] ?? null;
        $email_verified_at = $validated['email_verified_at'] ?? null;

        unset($validated['is_active'], $validated['confirmed_at'], $validated['email_verified_at']);

        $user = User::create($validated);

        // Set protected fields after creation
        $user->is_active = $is_active;
        if ($confirmed_at !== null) {
            $user->confirmed_at = $confirmed_at;
        }
        if ($email_verified_at !== null) {
            $user->email_verified_at = $email_verified_at;
        }
        $user->save();

        return response()->json(new UserWithRoleResource($user->load('roles')), 200);
    }

    public function show(User $user)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureUserBelongsToSchool($auth_user, $user);

        return response()->json(new UserWithRoleResource($user->load('roles')), 200);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureUserBelongsToSchool($auth_user, $user);
        $validated = $request->validated();

        $validated = $this->convertConfirmedVerified($validated, $user);

        // Extract protected fields before update since they're not fillable
        $has_is_active = array_key_exists('is_active', $validated);
        $has_confirmed_at = array_key_exists('confirmed_at', $validated);
        $has_email_verified_at = array_key_exists('email_verified_at', $validated);

        $is_active = $validated['is_active'] ?? null;
        $confirmed_at = $validated['confirmed_at'] ?? null;
        $email_verified_at = $validated['email_verified_at'] ?? null;

        if ($has_is_active && ! $is_active && $user->hasRole('super_admin')) {
            abort(403, 'Super-Admins können nicht deaktiviert werden.');
        }

        unset($validated['is_active'], $validated['confirmed_at'], $validated['email_verified_at'], $validated['id']);

        // Update fillable fields first
        $user->update($validated);

        // Update protected fields using raw DB query if any were provided
        if ($has_is_active || $has_confirmed_at || $has_email_verified_at) {
            $updates = [];
            if ($has_is_active) {
                $updates['is_active'] = $is_active;
            }
            if ($has_confirmed_at) {
                $updates['confirmed_at'] = $confirmed_at;
            }
            if ($has_email_verified_at) {
                $updates['email_verified_at'] = $email_verified_at;
            }
            DB::table('users')->where('id', $user->id)->update($updates);
            $user->refresh();
        }

        return response()->json(new UserWithRoleResource($user->load('roles')), 200);
    }

    private function convertConfirmedVerified($validated, $user = null)
    {

        // Benutzer is_confirmed?
        if ($validated['is_confirmed']) {
            if ($user) {
                if (! $user->confirmed_at) {
                    $validated['confirmed_at'] = now();
                }
            } else {
                $validated['confirmed_at'] = now();
            }
        } else {
            $validated['confirmed_at'] = null;
        }
        unset($validated['is_confirmed']);

        // E-Mail is_validated
        if ($validated['is_verified']) {
            if ($user) {
                if (! $user->email_verified_at) {
                    $validated['email_verified_at'] = now();
                }
            } else {
                $validated['email_verified_at'] = now();
            }
        } else {
            $validated['email_verified_at'] = null;
        }
        unset($validated['is_verified']);

        return $validated;
    }

    public function destroy(User $user)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureUserBelongsToSchool($auth_user, $user);

        if ($user->id == $auth_user->id) {
            abort(403, 'Man kann sich selbst nicht löschen');
        }
        if (! $user->shouldDelete()) {
            abort(403, 'Bei Löschen ist ein Fehler aufgetreten. Möglicherweise gibt es abhängige Daten.');
        }

        return response()->noContent();
    }

    public function destroyMultiple(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $ids = $request->all();

        $users = User::query()
            ->where('school_id', $auth_user->school_id)
            ->whereIn('id', $ids)
            ->get();

        if ($users->count() !== collect($ids)->unique()->count()) {
            abort(403, 'Mindestens ein Benutzer gehört nicht zu Ihrer Schule.');
        }

        $users->each(function ($user) use ($auth_user) {
            if ($user->id == $auth_user->id) {
                abort(403, 'Man kann sich selbst nicht löschen');
            }
            if (! $user->shouldDelete()) {
                abort(403, 'Bei Löschen ist ein Fehler aufgetreten. Möglicherweise gibt es abhängige Daten.');
            }
        });

        return response()->noContent();
    }

    public function updateProfile(UpdateProfileRequest $request, User $user)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->ensureUserBelongsToSchool($auth_user, $user);
        $validated = $request->validated();

        if ($user->email != $validated['email']) {
            // Neue E-Mail-Adresse, die muss natürlich zunächst bestätigt werden
            $adminService = new AdminService;
            $adminService->sendEmailValidationToken(1, $user, $validated['email']);

            return response()->json(['answer' => 'INPUT_CODE', 'email' => $user->email, 'email_new' => $validated['email']]);
        }

        $user->update($validated);

        return response()->json(new UserWithRoleResource($user->load('roles')), 200);
    }

    public function updateWithCode(UpdateUserWithCodeRequest $request)
    {
        if (! $user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        if (! $user->consumeToken2Fa($validated['token_2fa'])) {
            abort(401, 'Der Code ist falsch oder abgelaufen');
        }
        $validated['email_verified_at'] = now();
        $user->update($validated);

        return response()->json(new UserWithRoleResource($user->load('roles')), 200);
    }

    public function savePassword(SavePasswordRequest $request)
    {
        if (! $user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $adminService = new AdminService;

        $adminService->sendPasswordResetToken(1, $user, $user->email);

        $data = ['step' => 'PASSWORD_ENTER_TOKEN'];

        return response()->json($data, 200);
    }

    public function savePasswordWithCode(SavePasswordWithCodeRequest $request)
    {
        if (! $user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        if (! $user->consumeToken2Fa($validated['token_2fa'])) {
            abort(401, 'Kennwort speichern funktioniert nicht. Code falsch oder Zeit abgelaufen.');
        }

        $user->update(
            [
                'password' => Hash::make($validated['password']),
            ]
        );
    }

    private function ensureUserBelongsToSchool(User $authUser, User $user): void
    {
        if ((int) $authUser->school_id !== (int) $user->school_id) {
            abort(403, 'Der Benutzer gehört nicht zu Ihrer Schule.');
        }
    }
}
