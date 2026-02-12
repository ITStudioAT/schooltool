<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Homepage\SchoolWithLicenceRecource;
use App\Models\School;

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
}
