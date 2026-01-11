<?php

namespace App\Http\Resources\Homepage;

use App\Http\Resources\Homepage\SchoolResource;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tutoringFilter = $this->tutoring_filter ?: [
            'only_boys' => false,
            'only_girls' => false,
            'only_in_my_school' => true,
            'schools' => [],
        ];

        // Schools mit vollständigen Daten laden
        if (!empty($tutoringFilter['schools'])) {
            $schoolIds = collect($tutoringFilter['schools'])->pluck('id')->toArray();
            $schools = School::whereIn('id', $schoolIds)->get();

            $tutoringFilter['schools'] = $schools->map(function ($school) {
                return [
                    'id' => $school->id,
                    'school' => new SchoolResource($school),
                ];
            })->toArray();
        }

        return [
            'id' => $this->id,
            'email' => $this->email,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'sex' => $this->sex,
            'phone' => $this->phone,
            'schoolclass' => $this->schoolclass,
            'tutoring_filter' => $tutoringFilter,
        ];
    }
}
