<?php

return [
    'roles' => [
        // new entries detected
        '/admin/*' => ['super_admin', 'admin', 'register_admin', 'teacher', 'tutoring_admin'],
        '/admin/email_verification' => [],
        '/admin/login' => [],
        '/admin/register' => [],
        '/admin/unknown_password' => [],
    ]
];
