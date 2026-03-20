<?php

/* ===============================
Define the necessary roles for the routes
=============================== */

return [
    'roles' => [
        '/admin' => ['admin'],
        '/admin/dashboard' => ['admin'],
        '/admin/login' => [],
        '/admin/register' => [],
        '/admin/error' => [],
        '/admin/unknown_password' => [],
        '/admin/email_verification' => [],
        '/admin/profile' => ['admin'],
        '/admin/users' => ['admin'],
        '/admin/users/all_users' => ['admin'],
        '/admin/users/roles' => ['admin'],
        '/admin/users/users_with_roles' => ['admin'],
    ],
];
