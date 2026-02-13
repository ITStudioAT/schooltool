<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Homepage\SchoolWithLicenceRecource;
use App\Models\School;
use App\Services\StudentService;
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

        // Checken, ob Email in der Schule existiert
        $user = $service->isEmailValidForSchool($email, $school_id);


        if ($user) {
            // ##### User existiert

            if ($user->hasRole('student')) {
                // ##### User hat die Rolle eines student
                if ($type === 'login_with_password') {
                    // ##### Login mit Passwort

                } elseif ($type === 'login_without_password') {
                    // ##### Login ohne Passwort
                }
            } else {
                // ##### User hat die Rolle eines student nicht!

                // Prüfen, ob der User in der Import116 vorkommt

            }
        } else {
            // ##### User existiert nicht

            // Prüfen, ob der User in der Import116 vorkommt

        }







        Debugbar::info($request->all());
        return;
        $school_id = $request->input('school_id');
        $email = $request->input('email');


        $exists = School::where('contact_email', $email)->exists();

        return response()->json(['exists' => $exists], 200);
    }
}
