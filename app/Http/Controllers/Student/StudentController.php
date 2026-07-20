<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Homepage\SchoolWithLicenceRecource;
use App\Http\Resources\Teaching\UserResource;
use App\Models\SchoolTool;
use App\Services\LicenceService;
use App\Services\ParentStudentAccessService;
use App\Services\StudentService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function config(LicenceService $licenceService)
    {
        $schools = $licenceService->selectableSchoolsForTool('Lehrertool')['schools'];

        $data = [
            'schools' => SchoolWithLicenceRecource::collection($schools),
            'config' => [
                'schooltool' => [
                    'teaching_max_schools_shown' => (int) config('schooltool.teaching_max_schools_shown', 20),
                ],
            ],
            'health' => [
                'queue_working' => $this->isQueueWorking(),
            ],
        ];

        return response()->json($data, 200);
    }

    public function loginStepEmail(
        Request $request,
        StudentService $service,
        ParentStudentAccessService $parentAccess,
    ) {
        $validated = $request->validate([
            'type' => 'required|string|in:login_with_password,login_without_password',
            'school_id' => 'required|integer|exists:schools,id',
            'email' => 'required|email',
        ]);

        $type = $validated['type'];
        $school_id = $validated['school_id'];
        $email = $validated['email'];

        $schoolyear_id = SchoolTool::where('school_id', $school_id)->value('active_schoolyear_id');
        if (! $schoolyear_id) {
            abort(404, 'Keine aktive Schuljahr für die Schule gefunden. Wenden Sie sich an den Administrator.');
        }

        $userService = new UserService;
        $data = $validated;

        // Checken, ob Email in der Schule existiert
        $user = $service->isEmailValidForSchool($email, $school_id);

        $import_user = $service->isStudentInImport116($email, $school_id, $schoolyear_id);

        if (! $user && $import_user) {
            $user = $service->createUserFromImport116($import_user);
        }

        if ($user && $parentAccess->isActiveStudentUser($user)) {
            $parentAccess->clear();

            if ($type === 'login_with_password') {
                $data['status'] = 'enter_password';
            } elseif ($type === 'login_without_password') {
                $userService->sendCode($user, 'Ihr Login-Code für das Unterrichtstool', $email);
                $data['status'] = 'code_sent';
            }

            $data['login_context'] = 'student';
            $data['schoolyear_id'] = $schoolyear_id;

            return response()->json($data, 200);
        }

        if ($user && $import_user && $parentAccess->isActiveStudentUser($user, false)) {
            $user = $service->createUserFromImport116($import_user);
            $userService->sendCode($user, 'Ihr Login-Code für das Unterrichtstool', $email);

            $data['status'] = 'code_sent';
            $data['login_context'] = 'student';
            $data['schoolyear_id'] = $schoolyear_id;

            return response()->json($data, 200);
        }

        if ($import_user || ($user && $user->hasRole('student'))) {
            abort(403, 'Die E-Mail-Adresse ist nicht für diese Schule registriert. Bitte wenden Sie sich an Ihren Lehrer oder Administrator.');
        }

        if ($parentAccess->eligibleStudents($email, $school_id, $schoolyear_id)->isEmpty()) {
            abort(403, 'Die E-Mail-Adresse ist nicht für diese Schule registriert. Bitte wenden Sie sich an Ihren Lehrer oder Administrator.');
        }

        $parentAccess->startChallenge($email, $school_id, $schoolyear_id);
        $data['status'] = 'code_sent';
        $data['login_context'] = 'parent';
        $data['schoolyear_id'] = $schoolyear_id;

        return response()->json($data, 200);
    }

    public function loginStepCode(
        Request $request,
        StudentService $service,
        ParentStudentAccessService $parentAccess,
    ) {
        $validated = $request->validate([
            'type' => 'required|string|in:login_with_password,login_without_password',
            'school_id' => 'required|integer|exists:schools,id',
            'schoolyear_id' => 'required|integer|exists:schoolyears,id',
            'email' => 'required|email',
            'login_code' => 'required|string|size:6',
            'login_context' => 'nullable|string|in:student,parent',
        ]);
        $data = $validated;
        $school_id = $validated['school_id'];
        $email = $validated['email'];
        $this->ensureSchoolyearIsActive($school_id, $validated['schoolyear_id']);

        if (($validated['login_context'] ?? 'student') === 'parent') {
            if (! $parentAccess->verifyChallenge(
                $validated['login_code'],
                $email,
                $school_id,
                $validated['schoolyear_id'],
            )) {
                $data['status'] = 'code_not_valid';

                return response()->json($data, 200);
            }

            $data = array_merge($data, $this->parentStudentSelectionData($parentAccess));

            return response()->json($data, 200);
        }

        // Checken, ob Email in der Schule existiert
        $user = $service->isEmailValidForSchool($email, $school_id);
        if (! $user || ! $parentAccess->isActiveStudentUser($user)) {
            abort(403, 'Die E-Mail-Adresse ist nicht für diese Schule registriert. Bitte wenden Sie sich an Ihren Lehrer oder Administrator.');
        }

        if (! $user->consumeToken2Fa($validated['login_code'])) {
            // Code ist ungültig
            $data['status'] = 'code_not_valid';
        } else {
            // Code ist gültig
            $parentAccess->clear();
            $service->performLogin($user);
            $data['user'] = new UserResource($user);
            $data['viewer_type'] = 'student';
            $data['status'] = 'login_ok';
        }

        return response()->json($data, 200);
    }

    public function loginStepPassword(
        Request $request,
        StudentService $service,
        ParentStudentAccessService $parentAccess,
    ) {
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
        $this->ensureSchoolyearIsActive($school_id, $validated['schoolyear_id']);

        // Checken, ob Email in der Schule existiert
        $user = $service->isEmailValidForSchool($email, $school_id);
        if (! $user || ! $parentAccess->isActiveStudentUser($user)) {
            abort(403, 'Die E-Mail-Adresse ist nicht für diese Schule registriert. Bitte wenden Sie sich an Ihren Lehrer oder Administrator.');
        }

        if (! $service->isPasswordValid($user, $validated['password'])) {
            // Code ist ungültig
            $data['status'] = 'password_not_valid';
        } else {
            // Code ist gültig
            $parentAccess->clear();
            $service->performLogin($user);
            $data['user'] = new UserResource($user);
            $data['viewer_type'] = 'student';
            $data['status'] = 'login_ok';
        }

        return response()->json($data, 200);
    }

    public function loginStepParentStudent(
        Request $request,
        StudentService $studentService,
        ParentStudentAccessService $parentAccess,
    ) {
        $validated = $request->validate([
            'student_import_id' => ['required', 'integer'],
        ]);

        $student = $parentAccess->selectStudent((int) $validated['student_import_id'], $studentService);
        if (! $student) {
            abort(403, 'Dieses Kind kann für den Elternzugang nicht ausgewählt werden.');
        }

        return response()->json([
            'status' => 'login_ok',
            'viewer_type' => 'parent',
            'user' => new UserResource($student),
        ], 200);
    }

    public function parentStudents(ParentStudentAccessService $parentAccess): JsonResponse
    {
        if (! $parentAccess->isParentViewer()) {
            abort(403, 'Sie haben keine Berechtigung, ein Kind auszuwählen.');
        }

        return response()->json($this->parentStudentSelectionData($parentAccess), 200);
    }

    public function user(ParentStudentAccessService $parentAccess)
    {
        $user = $parentAccess->currentStudent();
        if ($user) {
            return response()->json([
                'user' => new UserResource($user),
                'viewer_type' => $parentAccess->isParentViewer() ? 'parent' : 'student',
            ], 200);
        }

        return response()->json([
            'user' => null,
            'viewer_type' => null,
        ], 200);
    }

    public function changePassword(Request $request, ParentStudentAccessService $parentAccess)
    {
        if (! $auth_user = $parentAccess->authenticatedStudent()) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'new_password' => 'required|string|min:8|max:255',
            'confirm_password' => 'required|string|min:8|max:255|same:new_password',
        ]);

        // Update password
        $auth_user->password = Hash::make($validated['new_password']);
        $auth_user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Passwort erfolgreich geändert',
        ], 200);
    }

    private function isQueueWorking(): bool
    {
        $workerAt = Cache::get('health:worker');
        if (! $workerAt) {
            return true;
        }

        return Carbon::parse($workerAt)->greaterThan(now()->subMinutes(2));
    }

    private function ensureSchoolyearIsActive(int $schoolId, int $schoolyearId): void
    {
        $activeSchoolyearId = SchoolTool::query()
            ->where('school_id', $schoolId)
            ->value('active_schoolyear_id');

        if ((int) $activeSchoolyearId !== $schoolyearId) {
            abort(403, 'Das Schuljahr ist für diese Schule nicht aktiv.');
        }
    }

    /** @return array<string, mixed> */
    private function parentStudentSelectionData(ParentStudentAccessService $parentAccess): array
    {
        $school = $parentAccess->school();
        $students = $parentAccess->verifiedStudents();

        if (! $school || $students->isEmpty()) {
            abort(403, 'Für diese E-Mail-Adresse wurde kein berechtigtes Kind gefunden.');
        }

        return [
            'status' => 'select_student',
            'school_id' => (int) $school->id,
            'selected_student_import_id' => $parentAccess->selectedStudentImportId(),
            'students' => $students->map(fn ($student): array => [
                'id' => (int) $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'name' => trim("{$student->first_name} {$student->last_name}"),
                'schoolclass' => $student->class,
                'age' => $student->birth_date?->age,
            ])->values(),
        ];
    }
}
