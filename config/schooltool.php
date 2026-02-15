<?php

return [
    'version' => '3.13.4',
    'copyright' => '(c) 2025–2026 ITStudio.at by Günther Kron',
    'logo' => 'schooltool_white.png',
    'pagination' => 30,
    'token_expire_time' => 60,
    'noreply_email' => 'noreply@schooltool.at',
    'sa_pw' => env('SA_PW'),
    'tutoring_active' => (bool) env('TUTORING_ACTIVE', false),
    'teaching_active' => (bool) env('TEACHING_ACTIVE', false),
    'teaching_max_schools_shown' => (int) env('TEACHING_MAX_SCHOOLS_SHOWN', 20),

    'schoolyears' => [
        ['name' => 'Schuljahr 2025/26', 'from' => '2025-09-08', 'sem_2_start' => '2026-02-16', 'to' => '2026-07-10', 'concerns' => '2025/26'],
        ['name' => 'Schuljahr 2026/27', 'from' => '2026-09-14', 'sem_2_start' => '2027-02-15', 'to' => '2027-07-09', 'concerns' => '2026/27'],
        ['name' => 'Schuljahr 2027/28', 'from' => '2027-09-13', 'sem_2_start' => '2028-02-21', 'to' => '2028-07-07', 'concerns' => '2027/28'],
        ['name' => 'Schuljahr 2028/29', 'from' => '2028-09-11', 'sem_2_start' => '2029-02-19', 'to' => '2029-07-06', 'concerns' => '2028/29'],
        ['name' => 'Schuljahr 2029/30', 'from' => '2029-09-10', 'sem_2_start' => '2030-02-18', 'to' => '2030-07-05', 'concerns' => '2029/30'],
        ['name' => 'Schuljahr 2030/31', 'from' => '2030-09-09', 'sem_2_start' => '2031-02-17', 'to' => '2031-07-04', 'concerns' => '2030/31'],
        ['name' => 'Schuljahr 2031/32', 'from' => '2031-09-08', 'sem_2_start' => '2032-02-16', 'to' => '2032-07-09', 'concerns' => '2031/32'],
        ['name' => 'Schuljahr 2032/33', 'from' => '2032-09-13', 'sem_2_start' => '2033-02-21', 'to' => '2033-07-08', 'concerns' => '2032/33'],
        ['name' => 'Schuljahr 2033/34', 'from' => '2033-09-12', 'sem_2_start' => '2034-02-20', 'to' => '2034-07-07', 'concerns' => '2033/34'],
        ['name' => 'Schuljahr 2034/35', 'from' => '2034-09-11', 'sem_2_start' => '2035-02-19', 'to' => '2035-07-06', 'concerns' => '2034/35'],
        ['name' => 'Schuljahr 2035/36', 'from' => '2035-09-10', 'sem_2_start' => '2036-02-18', 'to' => '2036-07-04', 'concerns' => '2035/36'],
    ],
];
