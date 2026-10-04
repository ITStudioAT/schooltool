<?php

use App\Http\Controllers\Admin\MaturaController;
use App\Http\Controllers\Homepage\HomepageController;
use App\Http\Controllers\MaturaStationController;
use App\Http\Controllers\TeachingCourseStudentEntryNotificationConfirmationController;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

// Broadcasting wird vom BroadcastServiceProvider gehandhabt

// Alles wird gethrottlet
Route::middleware(['throttle:global', 'throttle:web'])->group(function () {

    Route::get('/00-manager', [MaturaStationController::class, 'page'])->name('matura.station');
    Route::post('/00-manager/login', [MaturaStationController::class, 'login'])->middleware('throttle:60,1')->name('matura.login');
    Route::post('/00-manager/logout', [MaturaStationController::class, 'logout'])->name('matura.logout');
    Route::get('/00-manager/state', [MaturaStationController::class, 'state'])->withoutMiddleware('throttle:web')->middleware('throttle:matura')->name('matura.state');
    Route::post('/00-manager/action', [MaturaStationController::class, 'action'])->withoutMiddleware('throttle:web')->middleware('throttle:matura')->name('matura.action');

    Route::prefix('/admin/helpers/00-manager')->middleware(['auth:sanctum', 'web-allowed:scope:admin_shell_access'])->name('admin.matura.')->group(function (): void {
        Route::get('/', [MaturaController::class, 'index'])->name('index');
        Route::get('/roster', [MaturaController::class, 'roster'])->name('roster');
        Route::post('/', [MaturaController::class, 'store'])->name('store');
        Route::get('/{matura}', [MaturaController::class, 'show'])->name('show');
        Route::put('/{matura}', [MaturaController::class, 'update'])->name('update');
        Route::post('/{matura}/action', [MaturaController::class, 'action'])->name('action');
        Route::put('/{matura}/lifecycle', [MaturaController::class, 'lifecycle'])->name('lifecycle');
        Route::post('/{matura}/accesses', [MaturaController::class, 'invite'])->name('invite');
        Route::delete('/{matura}/accesses/{access}', [MaturaController::class, 'revoke'])->name('revoke');
        Route::get('/{matura}/report', [MaturaController::class, 'report'])->name('report');
        Route::get('/{matura}/pdf', [MaturaController::class, 'pdf'])->name('pdf');
    });

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
