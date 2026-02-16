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




    /* restliche admin-Routen */
    Route::get('/admin/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware(['auth:sanctum', 'web-allowed:admin,register_admin,tutoring_admin,teacher,lunch_admin']);


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

    if (config('schooltool.tutoring_active') === true) {


        // Your existing tutoring routes
        Route::prefix('homepage')->group(function () {

            Route::get('tutoring_response', fn() => view('homepage'));
            Route::get('tutoring_overview', fn() => view('homepage'));
            Route::get('tutoring', fn() => view('homepage'))->middleware(['auth:sanctum', 'tool-licensed:Nachhilfetool']);
        });

        // The three controller routes
        Route::prefix('homepage/tutoring')->group(function () {
            Route::get('confirm-user', [TutoringController::class, 'confirmUser']);
            Route::get('refuse-user', [TutoringController::class, 'refuseUser']);
            Route::get('offer', [\App\Http\Controllers\Tutoring\OfferController::class, 'offerConfirmRefuse']);
            Route::get('offer_request', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'offerRequest']);
        });
    }



    Route::get('/', function () {
        return view('homepage');
    });

    Route::get('/student/{any?}', function () {
        return view('homepage');
    })->where('any', '.*');

    Route::get('/homepage/{any?}',  [\App\Http\Controllers\Homepage\HomepageController::class, 'routing']);
});
