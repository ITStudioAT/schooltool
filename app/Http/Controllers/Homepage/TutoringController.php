<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homepage\TutoringCheckEmailRequest;
use App\Http\Requests\Homepage\TutoringCreateUserRequest;
use App\Http\Resources\Homepage\SchoolWithLicenceRecource;
use App\Models\School;
use App\Services\TutoringService;
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

    public function checkEmail(TutoringCheckEmailRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $data = $service->checkEmail($validated['data']);

        return response()->json($data, 200);
    }

    public function createUser(TutoringCreateUserRequest $request, TutoringService $service)
    {

        $validated = $request->validated();
        info($validated);
    }
}
