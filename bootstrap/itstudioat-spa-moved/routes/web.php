<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:global', 'throttle:web'])->group(function () {

    /***** ADMIN ROUTES *****/
    Route::get('/admin/login', function () {
        return view('spa::admin');
    })->name('login');
    Route::get('/admin/unknown_password', function () {
        return view('spa::admin');
    });
    Route::get('/admin/register', function () {
        abort_unless(config('spa.register_admin_allowed'), 403);

        return view('spa::admin');
    });
    Route::get('/admin/email_verification', function () {
        return view('spa::admin');
    });

    Route::middleware(['auth:sanctum', 'web-allowed:user,admin'])->group(function () {
        Route::get('/admin', function () {
            return view('spa::admin');
        });

        Route::get('/admin/profile', function () {
            return view('spa::admin');
        });
    });

    Route::middleware(['auth:sanctum', 'web-allowed:admin'])->group(function () {
        Route::get('/admin/dashboard', function () {
            return view('spa::admin');
        });

        Route::get('/admin/users', function () {
            return view('spa::admin');
        });

        Route::get('/admin/users/all_users', function () {
            return view('spa::admin');
        });

        Route::get('/admin/users/roles', function () {
            return view('spa::admin');
        });

        Route::get('/admin/users/users_with_roles', function () {
            return view('spa::admin');
        });
    });

    Route::middleware(['auth:sanctum', 'web-allowed:user,admin'])->group(function () {
        Route::get('/admin/{any?}', function () {
            return view('spa::admin');
        })->where('any', '.*');
    });

    Route::get('/application/{any?}', function () {
        return view('spa::application');
    })->where('any', '.*');

    Route::get('/{any?}', function () {
        return view('spa::homepage');
    });
});
