<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Http\Resources\Homepage\SchoolWithLicenceRecource;
use App\Models\School;
use Illuminate\Http\Request;

class TutoringController extends Controller
{
    public function config()
    {
        $licenceName = 'Tutoring';

        $schools = School::where('is_selectable', 1)->whereHas('licences', function ($query) use ($licenceName) {
            $query->where('name', $licenceName)
                ->where('school_licences.valid_until', '>=', now());
        })->with(['licences' => function ($query) use ($licenceName) {
            $query->where('name', $licenceName)
                ->where('school_licences.valid_until', '>=', now());
        }])->get();

        $data = [
            'schools' => SchoolWithLicenceRecource::collection($schools),
        ];

        return response()->json($data, 200);
    }
}
