<?php

use Illuminate\Support\Facades\Route;

// Alles wird gethrottlet

Route::middleware(['throttle:global', 'throttle:web', 'web-allowed'])->group(function () {

    /***** ADMIN ROUTES *****/
    /* auth-routes */
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




    /* restliche admin-Routen */
    Route::get('/admin/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware(['auth:sanctum']);


    /* APPLICATION ROUTES */
    Route::get('/application/{any?}', function () {
        return view('spa::application');
    })->where('any', '.*');

    /* HOMEPAGE ROUTES 
    Route::get('/', function () {
        return view('spa::homepage');
    });
    */

    /* WRONG ROUTES */
    Route::get('/{any?}', function () {
        return view('spa::homepage');
    });
});
