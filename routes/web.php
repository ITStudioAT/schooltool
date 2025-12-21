<?php

use App\Http\Controllers\Homepage\HomepageController;
use App\Http\Controllers\Tutoring\TutoringController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;



// Broadcasting wird vom BroadcastServiceProvider gehandhabt

// Alles wird gethrottlet

Route::middleware(['throttle:global', 'throttle:web'])->group(function () {

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




    Route::get('/homepage/tutoring/confirm-user',  [\App\Http\Controllers\Tutoring\TutoringController::class, 'confirmUser']);

    /* restliche admin-Routen */
    Route::get('/admin/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware(['auth:sanctum', 'web-allowed:admin, register_admin,tutoring_admin,teacher,lunch_admin']);


    /* APPLICATION ROUTES */
    Route::get('/application/{any?}', function () {
        return view('spa::application');
    })->where('any', '.*');

    Route::get('/homepage/register/', function () {
        return view('homepage');
    });

    Route::get('/homepage/register2/', function () {
        return view('homepage');
    });

    Route::get('/homepage/tutoring_overview/', function () {
        return view('homepage');
    });

    Route::get('/homepage/tutoring/', function () {
        return view('homepage');
    })->middleware(['auth:sanctum']);

    Route::get('/', function () {
        return view('homepage');
    });

    Route::get('/homepage/{any?}',  [\App\Http\Controllers\Homepage\HomepageController::class, 'routing']);
});
