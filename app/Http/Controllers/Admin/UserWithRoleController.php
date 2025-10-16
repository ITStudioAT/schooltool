<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
use App\Services\AdminService;
use App\Traits\PaginationTrait;

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

        $user = User::findOrFail($validated['id']);
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
        $query = User::orderBy('last_name')->orderBy('first_name');

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
        $validated['password'] = Hash::make(now());

        $user = User::create($validated);

        return response()->json(new UserWithRoleResource($user), 200);
    }

    public function show(User $user)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return response()->json(new UserWithRoleResource($user), 200);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $auth_user = $this->userHasRole(['admin']);
        $validated = $request->validated();

        $validated = $this->convertConfirmedVerified($validated, $user);

        $user->update($validated);

        return response()->json(new UserWithRoleResource($user), 200);
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

        User::whereIn('id', $ids)->each(function ($user) use ($auth_user) {
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
        $validated = $request->validated();

        if ($user->email != $validated['email']) {
            // Neue E-Mail-Adresse, die muss natürlich zunächst bestätigt werden
            $adminService = new AdminService();
            $adminService->sendEmailValidationToken(1, $user, $validated['email']);

            return response()->json(['answer' => 'INPUT_CODE', 'email' => $user->email, 'email_new' => $validated['email']]);
        }

        $user->update($validated);

        return response()->json(new UserWithRoleResource($user), 200);
    }

    public function updateWithCode(UpdateUserWithCodeRequest $request)
    {
        if (! $user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        if (! $user->checkToken2Fa($validated['token_2fa'])) {
            abort(401, 'Der Code ist falsch oder abgelaufen');
        }
        $validated['email_verified_at'] = now();
        $user->update($validated);

        return response()->json(new UserWithRoleResource($user), 200);
    }

    public function savePassword(SavePasswordRequest $request)
    {
        if (! $user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $adminService = new AdminService();

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

        if (! $user->checkToken2Fa($validated['token_2fa'])) {
            abort(401, 'Kennwort speichern funktioniert nicht. Code falsch oder Zeit abgelaufen.');
        }

        $user->update(
            [
                'password' => Hash::make($validated['password']),
            ]
        );
    }
}
