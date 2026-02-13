<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Homepage\SchoolWithLicenceRecource;
use App\Http\Resources\Teaching\UserResource;
use App\Models\School;
use App\Models\SchoolTool;
use App\Services\StudentService;
use App\Services\UserService;
use Fruitcake\LaravelDebugbar\Facades\Debugbar;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function config()
    {
        $licenceName = 'Lehrertool';

        $schools = School::where('is_selectable', 1)->whereHas('licences', function ($query) use ($licenceName) {
            $query->where('name', $licenceName)
                ->where('school_licences.valid_until', '>=', now());
        })->with(['licences' => function ($query) use ($licenceName) {
            $query->where('name', $licenceName)
                ->where('school_licences.valid_until', '>=', now());
        }])->orderBy('long_name')->get();

        $data = [
            'schools' => SchoolWithLicenceRecource::collection($schools),
            'config' => [
                'schooltool' => [
                    'teaching_max_schools_shown' => (int) config('schooltool.teaching_max_schools_shown', 20),
                ],
            ],
        ];

        return response()->json($data, 200);
    }

    public function loginStepEmail(Request $request, StudentService $service)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:login_with_password,login_without_password',
            'school_id' => 'required|integer|exists:schools,id',
            'email' => 'required|email',
        ]);

        $type = $validated['type'];
        $school_id = $validated['school_id'];
        $email = $validated['email'];

        $schoolyear_id = SchoolTool::where('school_id', $school_id)->value('active_schoolyear_id');
        if (!$schoolyear_id) abort(404, 'Keine aktive Schuljahr für die Schule gefunden. Wenden Sie sich an den Administrator.');



        $userService = new UserService();
        $data = $validated;

        // Checken, ob Email in der Schule existiert
        $user = $service->isEmailValidForSchool($email, $school_id);


        if (!$user) {
            // ##### User existiert nicht

            // Prüfen, ob der User in der Import116 vorkommt
            $import_user = $service->isStudentInImport116($email, $school_id, $schoolyear_id);
            if (!$import_user) abort(403, 'Die E-Mail-Adresse ist nicht für diese Schule registriert. Bitte wenden Sie sich an Ihren Lehrer oder Administrator.');

            $user = $service->createUserFromImport116($import_user);
        }


        if ($user) {
            // ##### User existiert
            if ($user->hasRole('student')) {
                // ##### User hat die Rolle eines student
                if ($type === 'login_with_password') {
                    // ##### Login mit Passwort
                    $data['status'] = 'enter_password';
                } elseif ($type === 'login_without_password') {
                    // ##### Login ohne Passwort
                    // Kennwort senden
                    $userService->sendCode($user, 'Ihr Login-Code für das Unterrichtstool', $email);
                    $data = $validated;
                    $data['status'] = 'code_sent';
                }
            } else {
                // ##### User hat die Rolle eines student nicht!

                // Prüfen, ob der User in der Import116 vorkommt
                $import_user = $service->isStudentInImport116($email, $school_id, $schoolyear_id);
                if (!$import_user) abort(403, 'Die E-Mail-Adresse ist nicht für diese Schule registriert. Bitte wenden Sie sich an Ihren Lehrer oder Administrator.');

                // Student-Rolle zuweisen
                $user->assignRole('student');

                // Kennwort senden
                $userService->sendCode($user, 'Ihr Login-Code für das Unterrichtstool', $email);

                $data['status'] = 'code_sent';
            }
        }

        $data['schoolyear_id'] = $schoolyear_id;

        return response()->json($data, 200);
    }

    public function loginStepCode(Request $request, StudentService $service)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:login_with_password,login_without_password',
            'school_id' => 'required|integer|exists:schools,id',
            'schoolyear_id' => 'required|integer|exists:schoolyears,id',
            'email' => 'required|email',
            'login_code' => 'required|string|size:6',
        ]);
        $data = $validated;
        $school_id = $validated['school_id'];
        $email = $validated['email'];

        // Checken, ob Email in der Schule existiert
        $user = $service->isEmailValidForSchool($email, $school_id);
        if (!$user) abort(403, 'Die E-Mail-Adresse ist nicht für diese Schule registriert. Bitte wenden Sie sich an Ihren Lehrer oder Administrator.');

        if (!$service->isTokenValid($user, $validated['login_code'])) {
            // Code ist ungültig
            $data['status'] = 'code_not_valid';
        } else {
            // Code ist gültig
            $service->performLogin($user);
            $data['user'] = new UserResource($user);
            $data['status'] = 'login_ok';
        }

        return response()->json($data, 200);
    }

    public function loginStepPassword(Request $request, StudentService $service)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:login_with_password,login_without_password',
            'school_id' => 'required|integer|exists:schools,id',
            'schoolyear_id' => 'required|integer|exists:schoolyears,id',
            'email' => 'required|email',
            'password' => 'required|string|min:8|max:255',
        ]);
        $data = $validated;
        $school_id = $validated['school_id'];
        $email = $validated['email'];

        // Checken, ob Email in der Schule existiert
        $user = $service->isEmailValidForSchool($email, $school_id);
        if (!$user) abort(403, 'Die E-Mail-Adresse ist nicht für diese Schule registriert. Bitte wenden Sie sich an Ihren Lehrer oder Administrator.');

        if (!$service->isPasswordValid($user, $validated['password'])) {
            // Code ist ungültig
            $data['status'] = 'password_not_valid';
        } else {
            // Code ist gültig
            $service->performLogin($user);
            $data['user'] = new UserResource($user);
            $data['status'] = 'login_ok';
        }
        return response()->json($data, 200);
    }
}
