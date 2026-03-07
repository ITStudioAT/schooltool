<?php

return [
    'roles' => [
        // new entries detected
        '/admin/groups' => ['super_admin', 'admin', 'materials_admin', 'materials_moderator'],
        '/admin/restaurant' => ['super_admin', 'admin', 'lunch_admin'],
        '/admin/aba' => ['super_admin', 'admin', 'aba_teacher'],
        '/admin/*' => ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher', 'lunch_admin', 'aba_teacher'],
        '/admin/email_verification' => [],
        '/admin/login' => [],
        '/admin/register' => [],
        '/admin/unknown_password' => [],
    ]
];
