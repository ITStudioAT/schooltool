<?php

it('removes the legacy route meta endpoint from the local spa package scaffold', function () {
    $projectRoot = dirname(__DIR__, 2);
    $packageApiRoutes = file_get_contents($projectRoot.'/bootstrap/itstudioat-spa-moved/routes/api.php');
    $packageAdminRouter = file_get_contents($projectRoot.'/bootstrap/itstudioat-spa-moved/resources/routes/admin.js');
    $packageProvider = file_get_contents($projectRoot.'/bootstrap/itstudioat-spa-moved/src/SpaServiceProvider.php');

    expect($packageApiRoutes)->not->toContain('/routes/is_route_allowed')
        ->and($packageAdminRouter)->not->toContain('/api/routes/is_route_allowed')
        ->and($packageAdminRouter)->toContain('/api/admin/config')
        ->and($packageProvider)->not->toContain('SyncRoutes::class')
        ->and($packageProvider)->not->toContain('RoutesSync::class')
        ->and(file_exists($projectRoot.'/bootstrap/itstudioat-spa-moved/src/Services/RouteService.php'))->toBeFalse()
        ->and(file_exists($projectRoot.'/bootstrap/itstudioat-spa-moved/src/Http/Controllers/Spa/RouteController.php'))->toBeFalse()
        ->and(file_exists($projectRoot.'/bootstrap/itstudioat-spa-moved/src/Enums/RouteResult.php'))->toBeFalse();
});

it('exposes capability based admin config in the local spa package scaffold', function () {
    $projectRoot = dirname(__DIR__, 2);
    $packageAdminController = file_get_contents($projectRoot.'/bootstrap/itstudioat-spa-moved/src/Http/Controllers/Admin/AdminController.php');
    $packageNavigationService = file_get_contents($projectRoot.'/bootstrap/itstudioat-spa-moved/src/Services/AdminNavigationService.php');

    expect($packageAdminController)->toContain("'capabilities' => \$navigationService->routeCapabilities(auth()->user(), \$menu)")
        ->and($packageNavigationService)->toContain('public function routeCapabilities')
        ->and($packageNavigationService)->toContain("'dashboard' => auth()->check() && (bool) \$this->userHasRole(['admin'])")
        ->and($packageNavigationService)->toContain("'users' => auth()->check() && (bool) \$this->userHasRole(['admin'])");
});

it('uses middleware based web access in the local spa package scaffold', function () {
    $projectRoot = dirname(__DIR__, 2);
    $packageWebRoutes = file_get_contents($projectRoot.'/bootstrap/itstudioat-spa-moved/routes/web.php');
    $packageWebAllowed = file_get_contents($projectRoot.'/bootstrap/itstudioat-spa-moved/src/Http/Middleware/WebAllowed.php');

    expect($packageWebRoutes)->toContain('web-allowed:user,admin')
        ->and($packageWebRoutes)->toContain('web-allowed:admin')
        ->and($packageWebAllowed)->not->toContain('RouteService')
        ->and($packageWebAllowed)->not->toContain('RouteResult')
        ->and($packageWebAllowed)->toContain('$this->userHasRole($allowedRoles)');
});
