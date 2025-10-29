<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Spa\RouteController;
use App\Http\Controllers\Admin\SpaRoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\UserWithRoleController;
use App\Http\Controllers\Homepage\HomepageController;




// Globales Throttle
Route::middleware(['api', 'throttle:global', 'throttle:api'])->group(function () {

    Route::get('admin/token', function (Request $request) {
        return csrf_token();
    });


    /***** OTHER ROUTES *****/
    Route::post('/routes/is_route_allowed',  [RouteController::class, 'isRouteAllowed']);
    Route::post('/admin/execute_logout',  [AdminController::class, 'executeLogout']);

    /***** HOMEPAGE ROUTES *****/


    Route::get('/homepage/config',  [HomepageController::class, 'config']);

    /***** ADMIN ROUTES *****/

    Route::get('/admin/config',  [AdminController::class, 'config']);

    Route::post('/admin/login_step_email',  [AdminController::class, 'loginStepEmail']);
    Route::post('/admin/login_step_2',  [AdminController::class, 'loginStep2']);
    Route::post('/admin/login_step_3',  [AdminController::class, 'loginStep3']);

    Route::post('/admin/password_unknown_step_1',  [AdminController::class, 'passwordUnknownStep1']);
    Route::post('/admin/password_unknown_step_2',  [AdminController::class, 'passwordUnknownStep2']);
    Route::post('/admin/password_unknown_step_3',  [AdminController::class, 'passwordUnknownStep3']);
    Route::post('/admin/password_unknown_step_4',  [AdminController::class, 'passwordUnknownStep4']);

    Route::post('/admin/register_step_1',  [AdminController::class, 'registerStep1']);
    Route::post('/admin/register_step_2',  [AdminController::class, 'registerStep2']);
    Route::post('/admin/register_step_3',  [AdminController::class, 'registerStep3']);

    /* vom User ausgelöste APis zur E-Mail-Verifikation */
    Route::post('/admin/users/send_verification_email_initialized_from_user',  [UserController::class, 'sendVerificationEmailInitializedFromUser']);
    Route::post('/admin/users/email_verification',  [UserController::class, 'emailVerification']);



    /* SANCTUM */
    Route::middleware(['auth:sanctum'])->group(function () {
        // navigation, menus
        Route::get('/admin/navigation/profile_menu',  [NavigationController::class, 'profileMenu']);
        Route::get('/admin/navigation/user_menu',  [NavigationController::class, 'userMenu']);

        // users
        Route::apiResource('/admin/users', UserController::class);
    });

    /* SANCTUM - user */
    Route::middleware(['auth:sanctum', 'api-allowed:user,admin,register_admin'])->group(function () {
        Route::put('/admin/users/update_profile/{user}',  [UserController::class, 'updateProfile']);
        Route::post('/admin/users/update_with_code',  [UserController::class, 'updateWithCode']);
        Route::post('/admin/users/save_password',  [UserController::class, 'savePassword']);
        Route::post('/admin/users/save_password_with_code',  [UserController::class, 'savePasswordWithCode']);
    });

    /* SANCTUM - admin, register_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,register_admin'])->group(function () {

        //licences
        Route::apiResource('/admin/licences', \App\Http\Controllers\Admin\LicenceController::class);

        //schools
        Route::apiResource('/admin/schools', \App\Http\Controllers\Admin\SchoolController::class);
        Route::post('/admin/schools_upload/uploadLogo', [\App\Http\Controllers\Admin\SchoolController::class, 'uploadLogo']);
        Route::patch('/admin/schools_upload/uploadLogo', [\App\Http\Controllers\Admin\SchoolController::class, 'uploadLogoNext']);
        Route::post('/admin/schools/delete_schools',  [\App\Http\Controllers\Admin\SchoolController::class, 'deleteSchools']);

        //schoolyears
        Route::apiResource('/admin/schoolyears', \App\Http\Controllers\Admin\SchoolyearController::class);
        Route::post('/admin/schoolyears/set_active',  [\App\Http\Controllers\Admin\SchoolyearController::class, 'setActiveSchoolyear']);

        // registers
        Route::apiResource('/admin/registers', \App\Http\Controllers\Admin\RegisterController::class);
        Route::post('/admin/registers/set_active',  [\App\Http\Controllers\Admin\RegisterController::class, 'setActiveRegister']);
        Route::post('/admin/registers/get_active',  [\App\Http\Controllers\Admin\RegisterController::class, 'getActiveRegisters']);
        Route::post('/admin/registers/toggle',  [\App\Http\Controllers\Admin\RegisterController::class, 'toggleRegister']);

        // register_dates
        Route::apiResource('/admin/register_dates', \App\Http\Controllers\Admin\RegisterDateController::class);
        Route::post('/admin/register_dates/create_dates',  [\App\Http\Controllers\Admin\RegisterDateController::class, 'createDates']);
        Route::post('/admin/register_dates/load_days',  [\App\Http\Controllers\Admin\RegisterDateController::class, 'loadDays']);
        Route::post('/admin/register_dates/filter_register_dates',  [\App\Http\Controllers\Admin\RegisterDateController::class, 'filterRegisterDates']);
        Route::post('/admin/register_dates/lock_register_dates',  [\App\Http\Controllers\Admin\RegisterDateController::class, 'lockRegisterDates']);
        Route::post('/admin/register_dates/unlock_register_dates',  [\App\Http\Controllers\Admin\RegisterDateController::class, 'unlockRegisterDates']);
        Route::post('/admin/register_dates/delete_register_dates',  [\App\Http\Controllers\Admin\RegisterDateController::class, 'deleteRegisterDates']);

        // register_date_bookings
        Route::apiResource('/admin/register_date_bookings', \App\Http\Controllers\Admin\RegisterDateBookingController::class);
        Route::post('/admin/register_date_bookings/get_user_with_email',  [\App\Http\Controllers\Admin\RegisterDateBookingController::class, 'getUserWithEmail']);
        Route::post('/admin/register_date_bookings/update_or_create_user',  [\App\Http\Controllers\Admin\RegisterDateBookingController::class, 'updateOrCreateUser']);
        Route::post('/admin/register_date_bookings/delete_bookings',  [\App\Http\Controllers\Admin\RegisterDateBookingController::class, 'deleteBookings']);
    });

    /* SANCTUM - admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin'])->group(function () {

        // users
        Route::post('/admin/users/destroy_multiple',  [UserController::class, 'destroyMultiple']);
        Route::post('/admin/users/send_verification_email',  [UserController::class, 'sendVerificationEmail']);
        Route::post('/admin/users/confirm',  [UserController::class, 'confirm']);
        Route::post('/admin/users/save_user_roles',  [UserController::class, 'saveUserRoles']);
        Route::post('/admin/users/save_2fa',  [UserController::class, 'save2Fa']);
        Route::post('/admin/users/save_2fa_with_code',  [UserController::class, 'save2FaWithCode']);


        // roles
        Route::apiResource('/admin/roles', SpaRoleController::class);
        Route::post('/admin/roles/destroy_multiple',  [SpaRoleController::class, 'destroyMultiple']);

        // users_with_roles
        Route::get('/admin/users_with_roles/roles',  [UserWithRoleController::class, 'roles']);
        Route::post('/admin/users_with_roles/roles',  [UserWithRoleController::class, 'saveUserRoles']);
        Route::apiResource('/admin/users_with_roles', UserWithRoleController::class);
    });
});
