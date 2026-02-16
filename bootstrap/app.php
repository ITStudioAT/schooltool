<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\ApiAllowed;
use App\Http\Middleware\WebAllowed;
use App\Http\Middleware\ToolLicensed;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->alias([
            'web-allowed' => WebAllowed::class,
            'api-allowed' => ApiAllowed::class,
            'tool-licensed' => ToolLicensed::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            '/broadcasting/auth',
            'broadcasting/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
