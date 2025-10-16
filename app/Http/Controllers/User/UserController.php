<?php

namespace App\Http\Controllers\User;

use App\Models\User;
use App\Enums\TwoFaResult;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\AdminService;
use App\Traits\PaginationTrait;
use App\Enums\VerificationResult;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\Admin\UserResource;
use App\Http\Requests\Admin\ConfirmRequest;
use App\Http\Requests\Admin\Save2FaRequest;
use App\Http\Requests\Admin\IndexUserRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\SavePasswordRequest;
use App\Http\Requests\Admin\SaveUserRolesRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Http\Requests\Admin\Save2FaWithCodeRequest;
use App\Http\Requests\Admin\EmailVerificationRequest;
use App\Http\Requests\Admin\UpdateUserWithCodeRequest;
use App\Http\Requests\Admin\SavePasswordWithCodeRequest;
use App\Http\Requests\Admin\SendVerificationMailRequest;
use App\Http\Requests\Admin\SendVerificationEmailInitializedFromUserRequest;

class UserController extends Controller
{
    use PaginationTrait;

    
    public function show(User $user)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return response()->json(new UserResource($user), 200);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $auth_user = $this->userHasRole(['admin']);
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

        return response()->json(new UserResource($user), 200);
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

        return response()->json(new UserResource($user), 200);
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

    public function save2Fa(Save2FaRequest $request)
    {
        if (! $user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $validated['email_2fa'] = $validated['email_2fa'] ?? null;

        if ($user->id != $validated['id']) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $userService = new UserService();

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
        if (! $user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $validated['email_2fa'] = $validated['email_2fa'] ?? null;

        if ($user->id != $validated['id']) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $userService = new UserService();

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

        $userService = new UserService();
        $userService->confirm($validated['ids']);
        //XXXXXXX
        return response()->json(VerificationResult::EMAIL_SENT, 200);
    }

    public function sendVerificationEmail(SendVerificationMailRequest $request)
    {
        if (! $user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $userService = new UserService();
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

        $userService = new UserService();
        $userService->sendVerificationEmail($user->id);

        return response()->json(VerificationResult::EMAIL_SENT, 200);
    }

    public function saveUserRoles(SaveUserRolesRequest $request)
    {
        if (! $user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $user_ids = $validated['user_ids'];
        $role_ids = $validated['role_ids'];

        $userService = new UserService();
        $userService->setNewUserRoles($user_ids, $role_ids);

        return response()->noContent();
    }
}
