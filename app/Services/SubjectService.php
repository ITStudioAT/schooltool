<?php

namespace App\Services;


class SubjectService
{

    public function create($school_id, $subjects)
    {
        foreach ($subjects as $subject) {
            if ($subject['short_name'] && $subject['long_name']) {
                // Filtere leere E-Mails raus
                $emailMentors = isset($subject['email_mentors'])
                    ? array_filter($subject['email_mentors'], function ($email) {
                        return !empty(trim($email));
                    })
                    : [];

                // Re-index array
                $emailMentors = array_values($emailMentors);

                \App\Models\TutoringSubject::firstOrCreate(
                    [
                        'school_id' => $school_id,
                        'short_name' => $subject['short_name'],
                    ],
                    [
                        'long_name' => $subject['long_name'],
                        'must_be_accepted' => $subject['must_be_accepted'] ?? false,
                        'email_mentors' => $emailMentors, // ✅ Immer ein Array (leer oder gefüllt)
                    ]
                );
            }
        }
    }
}
