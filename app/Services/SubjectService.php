<?php

namespace App\Services;


class SubjectService
{

    public function create($school_id, $subjects)
    {
        foreach ($subjects as $subject) {
            if ($subject['short_name'] && $subject['long_name']) {
                // Es müssen zumindest Kurzname und Langname gesetzt sein
                \App\Models\TutoringSubject::firstOrCreate(
                    [
                        'school_id' => $school_id,
                        'short_name' => $subject['short_name'] ?? null,
                    ],
                    [
                        'long_name' => $subject['long_name'] ?? null,
                        'email_mentor' => $subject['email_mentor'] ?? null,
                    ]
                );
            }
        }
    }
}
