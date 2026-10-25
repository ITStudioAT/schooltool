<?php

use App\Http\Controllers\Admin\Teaching\CourseDateController;
use App\Http\Controllers\Admin\Teaching\CurriculumUnitFileController;
use App\Http\Controllers\Admin\Teaching\WorkDropboxController;
use App\Http\Controllers\Homepage\HomepageController;
use App\Http\Controllers\MultipleChoiceParticipantController;
use App\Http\Controllers\NumberSystemPlayerController;
use App\Http\Controllers\SharedTeachingExplanationController;
use App\Http\Controllers\Student\CourseController;
use App\Http\Controllers\TeachingCourseStudentEntryNotificationConfirmationController;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

// Broadcasting wird vom BroadcastServiceProvider gehandhabt

// Alles wird gethrottlet
Route::middleware(['throttle:global', 'throttle:web'])->group(function () {

    Route::get('/explanations/{token}', [SharedTeachingExplanationController::class, 'entry'])->where('token', '[A-Za-z0-9]{48}')->name('explanations.entry');
    Route::get('/api/explanations/{token}', [SharedTeachingExplanationController::class, 'show'])->where('token', '[A-Za-z0-9]{48}')->name('explanations.show');

    Route::middleware(['auth', 'students-timetables-impersonation', 'api-allowed:scope:teaching_access', 'tool-licensed:Lehrertool,auth,scope:teaching_access'])->group(function (): void {
        Route::get('/learning-materials/curriculum/{file}', [CurriculumUnitFileController::class, 'explanation'])
            ->whereNumber('file')->name('curriculum-explanations.show');
        Route::get('/learning-materials/teaching/{attachment}', [CourseDateController::class, 'explanation'])
            ->whereNumber('attachment')->name('teaching-material-explanations.show');
    });
    Route::get('/learning-materials/course/{attachment}', [CourseController::class, 'explanation'])
        ->whereNumber('attachment')->middleware(['auth', 'students-timetables-impersonation', 'tool-licensed:Lehrertool'])->name('student-material-explanations.show');

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

    Route::get('/admin/teaching/dropbox/callback', [WorkDropboxController::class, 'callback'])
        ->middleware(['auth:sanctum', 'web-allowed:scope:tool_web_access', 'tool-licensed:Lehrertool,auth,scope:tool_web_access'])
        ->name('teaching.dropbox.callback');

    Route::get('/admin/teaching/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:scope:tool_web_access',
        'tool-licensed:Lehrertool,auth,scope:tool_web_access',
    ]);

    Route::get('/admin/teaching-tools/{any?}', function (): View {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:scope:tool_web_access',
        'tool-licensed:Lehrertool,auth,scope:tool_web_access',
    ])->name('admin.teaching-tools');

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

    Route::get('/student/games/number-systems/{run?}', function (): View {
        return view('homepage');
    })->whereNumber('run')->middleware('students-timetables-impersonation')->name('number-systems.entry');

    Route::get('/student/{any?}', function () {
        return view('homepage');
    })->where('any', '.*')->middleware('students-timetables-impersonation');

    Route::prefix('/api/number-systems')->middleware(['students-timetables-impersonation', 'throttle:120,1'])->name('number-systems.')->group(function (): void {
        Route::get('/', [NumberSystemPlayerController::class, 'index'])->name('index');
        Route::post('/join', [NumberSystemPlayerController::class, 'join'])->middleware('throttle:15,1')->name('join');
        Route::post('/lookup', [NumberSystemPlayerController::class, 'lookup'])->middleware('throttle:20,1')->name('lookup');
        Route::get('/runs/{run}', [NumberSystemPlayerController::class, 'show'])->name('show');
        Route::post('/runs/{run}', [NumberSystemPlayerController::class, 'update'])->name('update');
        Route::post('/runs/{run}/presence', [NumberSystemPlayerController::class, 'presence'])->name('presence');
    });

    Route::get('/multiple-choice', function (): View {
        return view('homepage');
    })->middleware('students-timetables-impersonation')->name('multiple-choice.entry');

    Route::prefix('/api/multiple-choice')->middleware(['students-timetables-impersonation', 'throttle:multiple-choice'])->name('multiple-choice.')->group(function (): void {
        Route::get('/attempts', [MultipleChoiceParticipantController::class, 'index'])->name('index');
        Route::get('/access', [MultipleChoiceParticipantController::class, 'access'])->middleware('throttle:multiple-choice-access')->name('access');
        Route::get('/attempts/{attempt}', [MultipleChoiceParticipantController::class, 'show'])->name('show');
        Route::post('/attempts/{attempt}/activity', [MultipleChoiceParticipantController::class, 'activity'])->middleware('throttle:multiple-choice-activity')->name('activity');
        Route::post('/attempts/{attempt}/start', [MultipleChoiceParticipantController::class, 'start'])->name('start');
        Route::post('/attempts/{attempt}/ready', [MultipleChoiceParticipantController::class, 'ready'])->name('ready');
        Route::put('/attempts/{attempt}/answers', [MultipleChoiceParticipantController::class, 'save'])->name('save');
        Route::post('/attempts/{attempt}/submit', [MultipleChoiceParticipantController::class, 'submit'])->name('submit');
    });

    Route::get('/students-timetables/{any?}', function () {
        return view('homepage');
    })->where('any', '.*');

    Route::get('/homepage/students-timetables/{any?}', function () {
        return view('homepage');
    })->where('any', '.*');

    Route::get('/homepage/{any?}', [HomepageController::class, 'routing']);

});
