<?php

use App\Http\Controllers\Homepage\HomepageController;
use App\Http\Controllers\TeachingCourseStudentEntryNotificationConfirmationController;
use App\Http\Controllers\Tutoring\OfferController;
use App\Http\Controllers\Tutoring\OfferRequestController;
use App\Http\Controllers\Tutoring\TutoringController;
use Illuminate\Contracts\View\View;
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
    Route::get('/admin/register_system/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:scope:tool_web_access',
        'tool-licensed:Anmeldetool,auth,scope:tool_web_access',
    ]);

    Route::get('/admin/tutoring/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:scope:tool_web_access',
        'tool-licensed:Nachhilfetool,auth,scope:tool_web_access',
    ]);

    Route::get('/admin/teaching/administration', function () {
        return view('spa::admin');
    })->middleware([
        'auth:sanctum',
        'web-allowed:scope:teaching_administration_access',
        'tool-licensed:Lehrertool,auth,scope:teaching_administration_access',
    ])->name('admin.teaching.administration');

    Route::get('/admin/teaching/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:scope:tool_web_access',
        'tool-licensed:Lehrertool,auth,scope:tool_web_access',
    ]);

    Route::get('/admin/materials/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:scope:materials_access',
        'tool-licensed:Materialientool,auth,scope:materials_access',
    ]);

    Route::get('/admin/materials-v2/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:scope:materials_access',
        'tool-licensed:Materialientool,auth,scope:materials_access',
    ]);

    Route::get('/admin/restaurant/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:scope:restaurant_access',
        'tool-licensed:Restaurant,auth,scope:restaurant_access',
    ]);

    Route::get('/admin/students-timetables/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:scope:students_timetables_access',
        'tool-licensed:StudentsTimetables,auth,scope:students_timetables_access',
    ]);

    Route::get('/admin/aba/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware(['auth:sanctum', 'aba-access']);

    Route::get('/admin/groups', function (): View {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        return view('spa::admin');
    })->middleware(['auth:sanctum', 'web-allowed:scope:super_admin_access'])->name('admin.groups');

    Route::get('/admin/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:scope:admin_shell_access',
    ]);

    /* APPLICATION ROUTES */
    Route::get('/application/{any?}', function () {
        return view('spa::application');
    })->where('any', '.*');

    Route::get('/homepage/register/', function () {
        return view('homepage');
    })->middleware('tool-licensed:Anmeldetool');

    Route::get('/homepage/register2/', function () {
        return view('homepage');
    })->middleware('tool-licensed:Anmeldetool');

    Route::prefix('homepage')->group(function () {
        Route::get('tutoring_response', fn () => view('homepage'));
        Route::get('tutoring_overview', fn () => view('homepage'))->middleware('tool-licensed:Nachhilfetool');
        Route::get('tutoring', fn () => view('homepage'))->middleware(['auth:sanctum', 'tool-licensed:Nachhilfetool']);
    });

    Route::prefix('homepage/tutoring')->group(function () {
        Route::get('confirm-user', [TutoringController::class, 'confirmUserPrompt'])
            ->middleware('signed')
            ->name('homepage.tutoring.confirm-user');
        Route::post('confirm-user', [TutoringController::class, 'confirmUser'])
            ->middleware('signed')
            ->name('homepage.tutoring.confirm-user.store');
        Route::get('refuse-user', [TutoringController::class, 'refuseUserPrompt'])
            ->middleware('signed')
            ->name('homepage.tutoring.refuse-user');
        Route::post('refuse-user', [TutoringController::class, 'refuseUser'])
            ->middleware('signed')
            ->name('homepage.tutoring.refuse-user.store');
        Route::get('offer', [OfferController::class, 'offerConfirmRefusePrompt'])
            ->middleware('signed')
            ->name('homepage.tutoring.offer');
        Route::post('offer', [OfferController::class, 'offerConfirmRefuse'])
            ->middleware('signed')
            ->name('homepage.tutoring.offer.store');
        Route::get('offer_request', [OfferRequestController::class, 'offerRequestPrompt'])
            ->middleware('signed')
            ->name('homepage.tutoring.offer-request');
        Route::post('offer_request', [OfferRequestController::class, 'offerRequest'])
            ->middleware('signed')
            ->name('homepage.tutoring.offer-request.store');
    });

    Route::get('teaching/entry-notifications/{notification}/confirm', [TeachingCourseStudentEntryNotificationConfirmationController::class, 'show'])
        ->middleware('signed')
        ->name('teaching-entry-notifications.confirm.show');
    Route::get('teaching/entry-notifications/{notification}/open', [TeachingCourseStudentEntryNotificationConfirmationController::class, 'open'])
        ->middleware('signed')
        ->name('teaching-entry-notifications.open');
    Route::post('teaching/entry-notifications/{notification}/confirm', [TeachingCourseStudentEntryNotificationConfirmationController::class, 'store'])
        ->middleware('signed')
        ->name('teaching-entry-notifications.confirm.store');

    Route::prefix('homepage/restaurant')->group(function () {
        Route::get('confirm-user', [HomepageController::class, 'restaurantConfirmUserPrompt'])
            ->middleware('signed')
            ->name('homepage.restaurant.confirm-user');
        Route::post('confirm-user', [HomepageController::class, 'restaurantConfirmUser'])
            ->middleware('signed')
            ->name('homepage.restaurant.confirm-user.store');
        Route::get('reject-user', [HomepageController::class, 'restaurantRejectUserPrompt'])
            ->middleware('signed')
            ->name('homepage.restaurant.reject-user');
        Route::post('reject-user', [HomepageController::class, 'restaurantRejectUser'])
            ->middleware('signed')
            ->name('homepage.restaurant.reject-user.store');
    });

    Route::get('/homepage/cashier/', function () {
        return view('homepage');
    });

    Route::get('/', function () {
        return view('homepage');
    });

    Route::get('/student/{any?}', function () {
        return view('homepage');
    })->where('any', '.*')->middleware('students-timetables-impersonation');

    Route::get('/students-timetables/{any?}', function () {
        return view('homepage');
    })->where('any', '.*');

    Route::get('/homepage/students-timetables/{any?}', function () {
        return view('homepage');
    })->where('any', '.*');

    Route::get('/homepage/{any?}', [HomepageController::class, 'routing']);

});
