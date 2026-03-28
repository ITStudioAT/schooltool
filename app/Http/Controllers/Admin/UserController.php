<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TwoFaResult;
use App\Enums\VerificationResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmRequest;
use App\Http\Requests\Admin\EmailVerificationRequest;
use App\Http\Requests\Admin\IndexUserRequest;
use App\Http\Requests\Admin\Save2FaRequest;
use App\Http\Requests\Admin\Save2FaWithCodeRequest;
use App\Http\Requests\Admin\SavePasswordRequest;
use App\Http\Requests\Admin\SavePasswordWithCodeRequest;
use App\Http\Requests\Admin\SaveUserRolesRequest;
use App\Http\Requests\Admin\SendVerificationEmailInitializedFromUserRequest;
use App\Http\Requests\Admin\SendVerificationMailRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UpdateUserWithCodeRequest;
use App\Http\Requests\Admin\UserDeleteUsersRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Requests\Admin\UserStoreUserRequest;
use App\Http\Requests\Admin\UserUpdateUserRequest;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use App\Services\AdminService;
use App\Services\UserService;
use App\Traits\PaginationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use PaginationTrait;

    public function loadUsers(UserIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;

        $role = $validated['role'] ?? null;

        $users = User::query()
            ->where('school_id', $auth_user->school_id)
            ->when($search_string, function ($query, $search_string) {
                $query->where(function ($q) use ($search_string) {
                    $q->where('last_name', 'like', "%{$search_string}%")
                        ->orWhere('first_name', 'like', "%{$search_string}%")
                        ->orWhere('email', 'like', "%{$search_string}%")
                        ->orWhere('schoolclass', 'like', "%{$search_string}%");
                });
            })
            ->when($role, function ($query, $role) {
                $query->role($role); // Spatie Permission Methode
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => new PaginateResource($users),
        ]);
    }

    public function updateUser(UserUpdateUserRequest $request, UserService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $user = $service->update($validated, $auth_user);

        return response()->json(new UserResource($user), 200);
    }

    public function storeUser(UserStoreUserRequest $request, UserService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $user = $service->store($auth_user->school_id, $validated);

        return response()->json(new UserResource($user), 200);
    }

    public function deleteUsers(UserDeleteUsersRequest $request, UserService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $service->delete($auth_user->id, $validated['data']);

        return response()->noContent();
    }

    public function index(IndexUserRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $search_model = $validated['search_model'] ?? [];
        $query = User::orderBy('last_name')->orderBy('first_name');

        // is_active
        if (isset($search_model['is_active'])) {
            if ($search_model['is_active'] === '1') {
                $query->where('is_active', true);
            } elseif ($search_model['is_active'] === '2') {
                $query->where('is_active', false);
            }
        }

        // is_confirmed (based on confirmed_at)
        if (isset($search_model['is_confirmed'])) {
            if ($search_model['is_confirmed'] === '1') {
                $query->whereNotNull('confirmed_at');
            } elseif ($search_model['is_confirmed'] === '2') {
                $query->whereNull('confirmed_at');
            }
        }

        // is_verified (based on email_verified_at)
        if (isset($search_model['is_verified'])) {
            if ($search_model['is_verified'] === '1') {
                $query->whereNotNull('email_verified_at');
            } elseif ($search_model['is_verified'] === '2') {
                $query->whereNull('email_verified_at');
            }
        }

        // is_2fa
        if (isset($search_model['is_2fa'])) {
            if ($search_model['is_2fa'] === '1') {
                $query->where('is_2fa', true);
            } elseif ($search_model['is_2fa'] === '2') {
                $query->where('is_2fa', false);
            }
        }

        // search_string (optional)
        if (! empty($search_model['search_string'])) {
            $search = $search_model['search_string'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $pagination = UserResource::collection($query->paginate(config('schooltool.pagination')));

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

        return response()->json(new UserResource($user), 200);
    }

    public function show(User $user)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin', 'tutoring_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return response()->json(new UserResource($user), 200);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $validated = $this->convertConfirmedVerified($validated, $user);

        $user->update($validated);

        return response()->json(new UserResource($user), 200);
    }

    public function destroy(User $user)
    {

        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($user->id == $auth_user->id) {
            abort(403, 'Man kann sich selbst nicht löschen');
        }

        $user->shouldDelete();

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
            $user->shouldDelete();
        });

        return response()->noContent();
    }

    private function convertConfirmedVerified($validated, $user = null)
    {

        // Benutzer is_confirmed?
        if (isset($validated['is_confirmed']) && $validated['is_confirmed']) {
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
        if (isset($validated['is_verified']) && $validated['is_verified']) {
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

    public function updateProfile(UpdateProfileRequest $request, User $user, AdminService $adminService)
    {

        if (! $auth_user = $this->userHasAtLeastOneRole()) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        unset($validated['id']);

        // Keine E-Mail-Änderung => Update durchführen und zwar für alle User in allen Schulen
        if ($user->email == $validated['email']) {
            User::where('email', $user->email)->update($validated);

            return response()->json(new UserResource($user), 200);
        }

        // Neue E-Mail-Adresse
        // Check, ob diese frei ist
        if (User::where('email', $validated['email'])->exists()) {
            abort(422, 'Diese E-Mail-Adresse wird bereits verwendet.');
        }

        // Token für 2FA setzen und E-Mail senden
        $data['school'] = $auth_user->selectedSchool;
        $email_90 = $user->email;
        $user->email = $validated['email'];
        $adminService->setToken2Fa($user, $data, 'Code für E-Mail-Änderung');
        $user->email = $email_90;
        $user->save();

        return response()->json(['answer' => 'INPUT_CODE', 'email' => $user->email, 'email_new' => $validated['email']]);
    }

    public function updateWithCode(UpdateUserWithCodeRequest $request)
    {
        if (! $user = $this->userHasAtLeastOneRole()) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        if (! $user->checkToken2Fa($validated['token_2fa'])) {
            abort(401, 'Der Code ist falsch oder abgelaufen');
        }

        $users = User::where('email', $user->email)->get();

        $ids = $users->pluck('id');

        unset($validated['id']);
        User::whereIn('id', $ids)->update($validated);

        return response()->json(new UserResource($user), 200);
    }

    public function savePassword(SavePasswordRequest $request, AdminService $adminService)
    {
        if (! $user = $this->userHasAtLeastOneRole()) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $data['school'] = $user->selectedSchool;
        $adminService->setToken2Fa($user, $data, 'Code für Kennwort-Änderung');

        $data = ['step' => 'PASSWORD_ENTER_TOKEN'];

        return response()->json($data, 200);
    }

    public function savePasswordWithCode(SavePasswordWithCodeRequest $request, AdminService $adminService)
    {
        if (! $user = $this->userHasAtLeastOneRole()) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        if (! $user->checkToken2Fa($validated['token_2fa'])) {
            abort(401, 'Kennwort speichern funktioniert nicht. Code falsch oder Zeit abgelaufen.');
        }

        $data['email'] = $user->email;
        $data['school'] = $user->selectedSchool;

        $users = User::where('email', $user->email)->get();
        $ids = $users->pluck('id');

        User::whereIn('id', $ids)->update(['password' => Hash::make($validated['password'])]);

        $adminService->login($data);

        return response()->noContent();
    }

    public function save2Fa(Save2FaRequest $request)
    {
        if (! $user = $this->userHasRole(['admin', 'tutoring_admin', 'register_admin', 'teaching_admin', 'materials_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $validated['email_2fa'] = $validated['email_2fa'] ?? null;

        if ($user->id != $validated['id']) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $userService = new UserService;

        $result = $userService->check2Fa($user, $validated['is_2fa'], $validated['email_2fa']);

        if ($result == TwoFaResult::TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL) {
            abort(422, 'Die E-Mail und die E-Mail für die 2-Faktoren-Authentifizierung dürfen nicht gleich sein');
        }
        if ($result == TwoFaResult::TWO_FA_ERROR) {
            abort(422, 'Fehler bei der 2-Faktoren-Authentifizierung');
        }

        $userService->check2FaStep2($result, $user, $validated['email_2fa']);

        $data = ['result' => $result];

        return response()->json($data, 200);
    }

    public function save2FaWithCode(Save2FaWithCodeRequest $request)
    {
        if (! $user = $this->userHasRole(['admin', 'tutoring_admin', 'register_admin', 'teaching_admin', 'materials_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $validated['email_2fa'] = $validated['email_2fa'] ?? null;

        if ($user->id != $validated['id']) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $userService = new UserService;

        $result = $userService->check2Fa($user, $validated['is_2fa'], $validated['email_2fa']);

        if ($result == TwoFaResult::TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL) {
            abort(422, 'Die E-Mail und die E-Mail für die 2-Faktoren-Authentifizierung dürfen nicht gleich sein');
        }
        if ($result == TwoFaResult::TWO_FA_ERROR) {
            abort(422, 'Fehler bei der 2-Faktoren-Authentifizierung');
        }

        if (! $user->checkToken2Fa($validated['token_2fa'])) {
            abort(401, 'Der Code ist falsch oder abgelaufen');
        }

        $userService->update2Fa($user, $validated['email_2fa']);

        $data = ['result' => TwoFaResult::TWO_FA_SET];

        return response()->json($data, 200);
    }

    public function confirm(ConfirmRequest $request)
    {
        if (! $user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $userService = new UserService;
        $userService->confirm($validated['ids']);

        // XXXXXXX
        return response()->json(VerificationResult::EMAIL_SENT, 200);
    }

    public function sendVerificationEmail(SendVerificationMailRequest $request)
    {
        if (! $user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $userService = new UserService;
        $userService->sendVerificationEmail($validated['ids']);

        return response()->json(VerificationResult::EMAIL_SENT, 200);
    }

    public function emailVerification(EmailVerificationRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if ($user->email_verified_at) {
            $user->emailVerified();

            return response()->json(VerificationResult::ALREADY_VERIFIED, 200);
        }

        if (! $user->checkUuid($validated['uuid'])) {
            abort(403, 'Die E-Mail-Verifikation hat nicht geklappt. Vermutlich ist die Zeit abgelaufen.');
        }

        $user->emailVerified();

        return response()->json(VerificationResult::VERIFICATION_SUCCESS, 200);
    }

    public function sendVerificationEmailInitializedFromUser(SendVerificationEmailInitializedFromUserRequest $request)
    {
        $validated = $request->validated();
        $user = User::where('email', $validated['email'])->first();

        $userService = new UserService;
        $userService->sendVerificationEmail($user->id);

        return response()->json(VerificationResult::EMAIL_SENT, 200);
    }

    public function saveUserRoles(SaveUserRolesRequest $request)
    {
        if (! $user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $user_ids = $validated['user_ids'];
        $role_ids = $validated['role_ids'];

        $userService = new UserService;
        $userService->setNewUserRoles($user_ids, $role_ids, $user);

        return response()->noContent();
    }

    public function toggleIsActive(Request $request, AdminService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin', 'admin', 'tutoring_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_ids' => ['nullable', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $userIds = collect($validated['user_ids'] ?? []);
        if (isset($validated['user_id'])) {
            $userIds->push((int) $validated['user_id']);
        }
        $userIds = $userIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            abort(422, 'Keine Benutzer ausgewählt.');
        }

        $usersQuery = User::whereIn('id', $userIds);
        if (! $auth_user->hasRole('super_admin')) {
            $usersQuery->where('school_id', $auth_user->school_id);
        }

        $users = $usersQuery->get();
        if ($users->count() !== $userIds->count()) {
            abort(403, 'Mindestens ein Benutzer wurde nicht gefunden oder gehört nicht zu deiner Schule.');
        }

        $protectedUser = $users->first(fn ($user) => $user->hasRole(['super_admin', 'admin']));
        if ($protectedUser) {
            abort(403, 'Der Super-Admin oder Admin kann nicht deaktiviert werden');
        }

        $forceActive = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : null;
        $changedCount = 0;

        foreach ($users as $user) {
            $newState = is_bool($forceActive) ? $forceActive : ! (bool) $user->is_active;
            if ((bool) $user->is_active === $newState) {
                continue;
            }

            $user->is_active = $newState;
            $user->save();
            $service->informUserToBeBlockedOrNot($user);
            $changedCount++;
        }

        if ($users->count() === 1) {
            return response()->json(new UserResource($users->first()), 200);
        }

        return response()->json([
            'updated_count' => $changedCount,
            'selected_count' => $users->count(),
            'ids' => $users->pluck('id')->values(),
        ], 200);
    }
}
