<?php

use App\Services\AccessScopeService;

it('resolves scope references to role names', function () {
    $service = app(AccessScopeService::class);

    expect($service->resolveRoleNames(['scope:materials_access']))
        ->toBe(['admin', 'materials_admin', 'materials_moderator']);
});

it('resolves the StudentsTimetables scope to admin roles', function () {
    $service = app(AccessScopeService::class);

    expect($service->resolveRoleNames(['scope:students_timetables_access']))
        ->toBe(['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator']);
});

it('resolves the StudentsTimetables Tests V3 scope to timetable admins', function () {
    $service = app(AccessScopeService::class);

    expect($service->resolveRoleNames(['scope:students_timetables_tests_v3_access']))
        ->toBe(['super_admin', 'admin', 'studentstimetables_admin']);
});

it('resolves tool web access to all module dashboard roles', function () {
    $service = app(AccessScopeService::class);

    expect($service->resolveRoleNames(['scope:tool_web_access']))
        ->toBe(['admin', 'register_admin', 'tutoring_admin', 'teaching_admin', 'teacher', 'lunch_admin']);
});

it('deduplicates role names when scopes and explicit roles overlap', function () {
    $service = app(AccessScopeService::class);

    expect($service->resolveRoleNames(['scope:materials_access', 'admin', 'materials_admin']))
        ->toBe(['admin', 'materials_admin', 'materials_moderator']);
});

it('throws for unknown scope references', function () {
    $service = app(AccessScopeService::class);

    expect(fn () => $service->resolveRoleNames(['scope:not_defined']))
        ->toThrow(InvalidArgumentException::class, 'Unknown access scope [not_defined].');
});
