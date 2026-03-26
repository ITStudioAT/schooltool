<?php

use App\Services\AccessScopeService;

it('resolves scope references to role names', function () {
    $service = app(AccessScopeService::class);

    expect($service->resolveRoleNames(['scope:materials_access']))
        ->toBe(['admin', 'materials_admin', 'materials_moderator']);
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
