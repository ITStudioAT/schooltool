<?php

return [
    'roles' => [
        // new entries detected
        '/admin/groups' => ['super_admin', 'admin', 'materials_admin', 'materials_moderator'],
        '/admin/*' => ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin', 'teacher'],
        '/admin/email_verification' => [],
        '/admin/login' => [],
        '/admin/register' => [],
        '/admin/unknown_password' => [],
    ]
];
