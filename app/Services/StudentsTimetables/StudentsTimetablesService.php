<?php

namespace App\Services\StudentsTimetables;

use App\Models\User;

class StudentsTimetablesService
{
    /**
     * @return array{
     *     module:string,
     *     school:array{id:int|null,name:string|null},
     *     status:string,
     *     items:array<int, array<string, string>>
     * }
     */
    public function dashboardForUser(User $user): array
    {
        return [
            'module' => 'StudentsTimetables',
            'school' => [
                'id' => $user->selectedSchool?->id,
                'name' => $user->selectedSchool?->long_name,
            ],
            'status' => 'dummy',
            'items' => [],
        ];
    }
}
