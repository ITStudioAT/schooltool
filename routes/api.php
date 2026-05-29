<?php

use App\Http\Controllers\Admin\ABA\AbaAttachmentController;
use App\Http\Controllers\Admin\ABA\AbaChunkUploadController;
use App\Http\Controllers\Admin\ABA\AbaController;
use App\Http\Controllers\Admin\ABA\AbaExtractionController;
use App\Http\Controllers\Admin\ABA\AbaSettingsController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\LicenceController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\Materials\MaterialChunkUploadController;
use App\Http\Controllers\Admin\Materials\MaterialClassificationController;
use App\Http\Controllers\Admin\Materials\MaterialController;
use App\Http\Controllers\Admin\Materials\MaterialFileSettingsController;
use App\Http\Controllers\Admin\Materials\MaterialShareController;
use App\Http\Controllers\Admin\Materials\MaterialStatusController;
use App\Http\Controllers\Admin\Materials\MaterialStorageAuditController;
use App\Http\Controllers\Admin\Materials\MaterialTypeController;
use App\Http\Controllers\Admin\Materials\MaterialUserSettingsController;
use App\Http\Controllers\Admin\Materials\MaterialWorkspaceController;
use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\RegisterDateBookingController;
use App\Http\Controllers\Admin\RegisterDateController;
use App\Http\Controllers\Admin\RegisterPrintController;
use App\Http\Controllers\Admin\RegisterUserController;
use App\Http\Controllers\Admin\Restaurant\RestaurantBillingController;
use App\Http\Controllers\Admin\Restaurant\RestaurantCategoryController;
use App\Http\Controllers\Admin\Restaurant\RestaurantCdgymLegacyImportController;
use App\Http\Controllers\Admin\Restaurant\RestaurantCdgymLegacyStatsController;
use App\Http\Controllers\Admin\Restaurant\RestaurantEatingTimeController;
use App\Http\Controllers\Admin\Restaurant\RestaurantFoodController;
use App\Http\Controllers\Admin\Restaurant\RestaurantFreeDayController;
use App\Http\Controllers\Admin\Restaurant\RestaurantGeneralSettingsController;
use App\Http\Controllers\Admin\Restaurant\RestaurantIngredientIconController;
use App\Http\Controllers\Admin\Restaurant\RestaurantMenuController;
use App\Http\Controllers\Admin\Restaurant\RestaurantMenuPlanController;
use App\Http\Controllers\Admin\Restaurant\RestaurantOnlineSettingsController;
use App\Http\Controllers\Admin\Restaurant\RestaurantSepaSettingsController;
use App\Http\Controllers\Admin\Restaurant\RestaurantSettingsController;
use App\Http\Controllers\Admin\Restaurant\RestaurantUserController;
use App\Http\Controllers\Admin\Restaurant\RestaurantUserSettingsController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SchoolToolController;
use App\Http\Controllers\Admin\SchoolyearController;
use App\Http\Controllers\Admin\SpaRoleController;
use App\Http\Controllers\Admin\StudentsTimetables\AdminUserController as StudentsTimetablesAdminUserController;
use App\Http\Controllers\Admin\StudentsTimetables\RecognitionCsvUploadController;
use App\Http\Controllers\Admin\StudentsTimetables\StudentsTimetablesController;
use App\Http\Controllers\Admin\StudentsTimetables\SubjectOverviewJsonUploadController;
use App\Http\Controllers\Admin\StudentsTimetables\TimetableFileUploadController;
use App\Http\Controllers\Admin\StudentsTimetables\TimetableImportController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\TeachersListController;
use App\Http\Controllers\Admin\Teaching\CourseBehaviourEntryController;
use App\Http\Controllers\Admin\Teaching\CourseDateController;
use App\Http\Controllers\Admin\Teaching\CourseStudentCategoryEvaluationController;
use App\Http\Controllers\Admin\Teaching\CourseWorkController;
use App\Http\Controllers\Admin\Teaching\CurriculumController;
use App\Http\Controllers\Admin\Teaching\CurriculumDocumentController;
use App\Http\Controllers\Admin\Teaching\CurriculumExportController;
use App\Http\Controllers\Admin\Teaching\FileUploadController;
use App\Http\Controllers\Admin\Teaching\HolidayController;
use App\Http\Controllers\Admin\Teaching\Import116Controller;
use App\Http\Controllers\Admin\Teaching\ImportedCurriculumController;
use App\Http\Controllers\Admin\Teaching\MyHolidayController;
use App\Http\Controllers\Admin\Teaching\SchoolHourController;
use App\Http\Controllers\Admin\Teaching\TeachingBackupController;
use App\Http\Controllers\Admin\Teaching\TeachingController;
use App\Http\Controllers\Admin\Teaching\TeachingCourseController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserHopperAccountController;
use App\Http\Controllers\Admin\UserWithRoleController;
use App\Http\Controllers\Homepage\HomepageController;
use App\Http\Controllers\Homepage\NoteController;
use App\Http\Controllers\Homepage\RegisterController;
use App\Http\Controllers\Homepage\RestaurantBookingController;
use App\Http\Controllers\Student\CourseController;
use App\Http\Controllers\Student\CourseStudentEntryController;
use App\Http\Controllers\Student\StudentController;
use App\Http\Controllers\Tutoring\OfferController;
use App\Http\Controllers\Tutoring\OfferRequestController;
use App\Http\Controllers\Tutoring\SubjectController;
use App\Http\Controllers\Tutoring\TutoringController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Globales Throttle
Route::middleware(['api', 'throttle:global', 'throttle:api'])->group(function () {

    Route::get('admin/token', function (Request $request) {
        return csrf_token();
    });

    /***** OTHER ROUTES *****/
    Route::post('/admin/execute_logout', [AdminController::class, 'executeLogout']);

    /***** HOMEPAGE ROUTES *****/
    Route::get('/homepage/login_schools', [HomepageController::class, 'loginSchools']);
    Route::post('/homepage/login_step_email', [HomepageController::class, 'homepageLoginStepEmail']);
    Route::post('/homepage/login_step_password', [HomepageController::class, 'homepageLoginStepPassword']);
    Route::post('/homepage/login_step_2fa', [HomepageController::class, 'homepageLoginStep2fa']);
    Route::get('/homepage/config', [HomepageController::class, 'config']);
    Route::get('/homepage/load_schools_for_tool', [HomepageController::class, 'loadSchoolsForTool']);
    Route::get('/homepage/restaurant/menu-plans', [HomepageController::class, 'restaurantMenuPlans']);
    Route::get('/homepage/restaurant/menu-plans/{id}/print', [HomepageController::class, 'restaurantMenuPlanPrint']);
    Route::post('/homepage/restaurant/check_email', [HomepageController::class, 'restaurantCheckEmail'])->middleware('tool-licensed:Restaurant');
    Route::post('/homepage/restaurant/send_login_code', [HomepageController::class, 'restaurantSendLoginCode'])->middleware('tool-licensed:Restaurant');
    Route::post('/homepage/restaurant/login_with_code', [HomepageController::class, 'restaurantLoginWithCode'])->middleware('tool-licensed:Restaurant');
    Route::post('/homepage/restaurant/login_with_password', [HomepageController::class, 'restaurantLoginWithPassword'])->middleware('tool-licensed:Restaurant');
    Route::post('/homepage/restaurant/change_password', [HomepageController::class, 'restaurantChangePassword'])->middleware('tool-licensed:Restaurant');
    Route::post('/homepage/restaurant/register', [HomepageController::class, 'restaurantRegisterUser'])->middleware('tool-licensed:Restaurant');
    Route::post('/homepage/restaurant/confirm_email', [HomepageController::class, 'restaurantConfirmEmail'])->middleware('tool-licensed:Restaurant');
    Route::post('/homepage/restaurant/sepa/store', [HomepageController::class, 'restaurantStoreSepaMandate'])->middleware('tool-licensed:Restaurant');
    Route::post('/homepage/restaurant/sepa/confirm_code', [HomepageController::class, 'restaurantConfirmSepaMandateCode'])->middleware('tool-licensed:Restaurant');
    Route::post('/homepage/restaurant/sepa/resend_code', [HomepageController::class, 'restaurantResendSepaMandateCode'])->middleware('tool-licensed:Restaurant');
    Route::post('/homepage/restaurant/sepa/complete', [HomepageController::class, 'restaurantCompleteSepaMandate'])->middleware('tool-licensed:Restaurant');

    // Restaurant booking routes
    Route::post('/homepage/restaurant/bookings', [RestaurantBookingController::class, 'store'])->middleware(['auth:sanctum', 'tool-licensed:Restaurant']);
    Route::get('/homepage/restaurant/bookings', [RestaurantBookingController::class, 'index'])->middleware(['auth:sanctum', 'tool-licensed:Restaurant']);
    Route::get('/homepage/restaurant/print', [RestaurantBookingController::class, 'print'])->middleware(['auth:sanctum', 'tool-licensed:Restaurant']);
    Route::delete('/homepage/restaurant/bookings/{id}', [RestaurantBookingController::class, 'destroy'])->middleware(['auth:sanctum', 'tool-licensed:Restaurant']);
    Route::get('/homepage/restaurant/child-options', [RestaurantBookingController::class, 'childOptions'])->middleware(['auth:sanctum', 'tool-licensed:Restaurant']);

    Route::post('/homepage/logout', [HomepageController::class, 'logout']);

    /***** STUDENT ROUTES *****/
    Route::get('/homepage/student/config', [StudentController::class, 'config']);
    Route::post('/homepage/student/login_step_email', [StudentController::class, 'loginStepEmail'])->middleware('tool-licensed:Lehrertool');
    Route::post('/homepage/student/login_step_code', [StudentController::class, 'loginStepCode'])->middleware('tool-licensed:Lehrertool');
    Route::post('/homepage/student/login_step_password', [StudentController::class, 'loginStepPassword'])->middleware('tool-licensed:Lehrertool');
    Route::get('/homepage/student/user', [StudentController::class, 'user'])->middleware('tool-licensed:Lehrertool');
    Route::post('/homepage/student/change_password', [StudentController::class, 'changePassword'])->middleware('tool-licensed:Lehrertool');
    Route::get('/homepage/student/courses', [CourseController::class, 'index'])->middleware('tool-licensed:Lehrertool');
    Route::get('/homepage/student/courses/{courseId}', [CourseController::class, 'show'])->middleware('tool-licensed:Lehrertool');
    Route::get('/homepage/student/courses/{courseId}/entries', [CourseStudentEntryController::class, 'index'])->middleware('tool-licensed:Lehrertool');
    Route::get('/homepage/student/course-date-materials/attachments/{attachment}/preview', [CourseController::class, 'previewAdoptedAttachment'])->middleware('tool-licensed:Lehrertool');
    Route::get('/homepage/student/course-date-materials/attachments/{attachment}/download', [CourseController::class, 'downloadAdoptedAttachment'])->middleware('tool-licensed:Lehrertool');

    /***** ADMIN ROUTES *****/
    Route::get('/admin/config', [AdminController::class, 'config']);

    Route::middleware(['auth:sanctum', 'api-allowed:scope:students_timetables_access', 'tool-licensed:StudentsTimetables,auth,scope:students_timetables_access'])->group(function () {
        Route::get('/admin/students-timetables', [StudentsTimetablesController::class, 'index']);
        Route::get('/admin/students-timetables/admin-users', [StudentsTimetablesAdminUserController::class, 'index'])
            ->defaults('managedRole', 'studentstimetables_admin');
        Route::post('/admin/students-timetables/admin-users', [StudentsTimetablesAdminUserController::class, 'store'])
            ->defaults('managedRole', 'studentstimetables_admin');
        Route::put('/admin/students-timetables/admin-users/{adminUser}', [StudentsTimetablesAdminUserController::class, 'update'])
            ->defaults('managedRole', 'studentstimetables_admin');
        Route::post('/admin/students-timetables/admin-users/toggle-active', [StudentsTimetablesAdminUserController::class, 'toggleActive'])
            ->defaults('managedRole', 'studentstimetables_admin');
        Route::get('/admin/students-timetables/moderator-users', [StudentsTimetablesAdminUserController::class, 'index'])
            ->defaults('managedRole', 'studentstimetables_moderator');
        Route::post('/admin/students-timetables/moderator-users', [StudentsTimetablesAdminUserController::class, 'store'])
            ->defaults('managedRole', 'studentstimetables_moderator');
        Route::put('/admin/students-timetables/moderator-users/{adminUser}', [StudentsTimetablesAdminUserController::class, 'update'])
            ->defaults('managedRole', 'studentstimetables_moderator');
        Route::post('/admin/students-timetables/moderator-users/toggle-active', [StudentsTimetablesAdminUserController::class, 'toggleActive'])
            ->defaults('managedRole', 'studentstimetables_moderator');
        Route::get('/admin/students-timetables/school-hours', [StudentsTimetablesController::class, 'schoolHours']);
        Route::get('/admin/students-timetables/course-groups', [StudentsTimetablesController::class, 'courseGroups']);
        Route::get('/admin/students-timetables/robot/students', [StudentsTimetablesController::class, 'robotStudents']);
        Route::get('/admin/students-timetables/robot/student-completed-courses', [StudentsTimetablesController::class, 'robotStudentCompletedCourses']);
        Route::post('/admin/students-timetables/robot/backend-timetable', [StudentsTimetablesController::class, 'robotBackendTimetable']);
        Route::post('/admin/students-timetables/robot/quality-counters', [StudentsTimetablesController::class, 'robotQualityCounters']);
        Route::post('/admin/students-timetables/robot/full-green-count', [StudentsTimetablesController::class, 'robotFullGreenCount']);
        Route::get('/admin/students-timetables/evaluation-settings', [StudentsTimetablesController::class, 'evaluationSettings']);
        Route::put('/admin/students-timetables/evaluation-settings', [StudentsTimetablesController::class, 'updateEvaluationSettings']);
        Route::get('/admin/students-timetables/overview-selections', [StudentsTimetablesController::class, 'overviewSelections']);
        Route::put('/admin/students-timetables/overview-selections', [StudentsTimetablesController::class, 'updateOverviewSelections']);
        Route::get('/admin/students-timetables/subjects-overview-json', [SubjectOverviewJsonUploadController::class, 'index']);
        Route::post('/admin/students-timetables/subjects-overview-json', [SubjectOverviewJsonUploadController::class, 'upload']);
        Route::patch('/admin/students-timetables/subjects-overview-json', [SubjectOverviewJsonUploadController::class, 'uploadNext']);
        Route::get('/admin/students-timetables/subjects-overview-settings', [SubjectOverviewJsonUploadController::class, 'settings']);
        Route::put('/admin/students-timetables/subjects-overview-settings/subjects', [SubjectOverviewJsonUploadController::class, 'updateSubjects']);
        Route::put('/admin/students-timetables/subjects-overview-settings/mappings', [SubjectOverviewJsonUploadController::class, 'updateMappings']);
        Route::post('/admin/students-timetables/upload', [TimetableFileUploadController::class, 'upload']);
        Route::patch('/admin/students-timetables/upload', [TimetableFileUploadController::class, 'uploadNext']);
        Route::get('/admin/students-timetables/recognitions-csv', [RecognitionCsvUploadController::class, 'index']);
        Route::post('/admin/students-timetables/recognitions-csv', [RecognitionCsvUploadController::class, 'upload']);
        Route::patch('/admin/students-timetables/recognitions-csv', [RecognitionCsvUploadController::class, 'uploadNext']);
        Route::delete('/admin/students-timetables/recognitions-csv/{recognitionImport}', [RecognitionCsvUploadController::class, 'destroy'])
            ->whereNumber('recognitionImport');
        Route::get('/admin/students-timetables/imports', [TimetableImportController::class, 'index']);
        Route::put('/admin/students-timetables/imports/single-date-appointments', [TimetableImportController::class, 'updateSingleDateAppointments']);
        Route::get('/admin/students-timetables/imports/{timetableImport}', [TimetableImportController::class, 'show']);
        Route::delete('/admin/students-timetables/imports/{timetableImport}', [TimetableImportController::class, 'destroy']);
    });

    Route::post('/admin/login_step_email', [AdminController::class, 'loginStepEmail']);
    Route::post('/admin/login_step_2', [AdminController::class, 'loginStep2']);
    Route::post('/admin/login_step_3', [AdminController::class, 'loginStep3']);

    Route::post('/admin/new_teacher_step_email', [AdminController::class, 'newTeacherStepEmail']);
    Route::post('/admin/new_teacher_step_school', [AdminController::class, 'newTeacherStepSchool']);
    Route::post('/admin/new_teacher_step_code', [AdminController::class, 'newTeacherStepCode']);

    Route::post('/admin/password_unknown_step_email', [AdminController::class, 'passwordUnknownStepEmail']);
    Route::post('/admin/password_unknown_step_school', [AdminController::class, 'passwordUnknownStepSchool']);
    Route::post('/admin/password_unknown_step_token', [AdminController::class, 'passwordUnknownStepToken']);
    Route::post('/admin/password_unknown_step_token_2', [AdminController::class, 'passwordUnknownStepToken2']);
    Route::post('/admin/password_unknown_step_password', [AdminController::class, 'passwordUnknownStepPassword']);

    Route::post('/admin/register_step_1', [AdminController::class, 'registerStep1']);
    Route::post('/admin/register_step_2', [AdminController::class, 'registerStep2']);
    Route::post('/admin/register_step_3', [AdminController::class, 'registerStep3']);

    /* vom User ausgelöste APis zur E-Mail-Verifikation */
    Route::post('/admin/users/send_verification_email_initialized_from_user', [UserController::class, 'sendVerificationEmailInitializedFromUser']);
    Route::post('/admin/users/email_verification', [UserController::class, 'emailVerification']);

    /* homepage/register */
    Route::get('/homepage/register/config', [RegisterController::class, 'config'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/check_email', [RegisterController::class, 'checkEmail'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/confirm_email', [RegisterController::class, 'confirmEmail'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/save_user_data', [RegisterController::class, 'saveUserData'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/login_token', [RegisterController::class, 'loginToken'])->middleware('tool-licensed:Anmeldetool');
    Route::get('/homepage/register/load_register_and_user', [RegisterController::class, 'loadRegisterAndUser'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/book', [RegisterController::class, 'book'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/delete_booking', [RegisterController::class, 'deleteBooking'])->middleware('tool-licensed:Anmeldetool');

    /* homepage/tutoring */
    Route::get('/homepage/tutoring/config', [TutoringController::class, 'config']);
    Route::post('/homepage/tutoring/check_email', [TutoringController::class, 'checkEMail'])->middleware('tool-licensed:Nachhilfetool');
    Route::post('/homepage/tutoring/confirm_email', [TutoringController::class, 'confirmEMail'])->middleware('tool-licensed:Nachhilfetool');
    Route::post('/homepage/tutoring/create_user', [TutoringController::class, 'createUser'])->middleware('tool-licensed:Nachhilfetool');
    Route::post('/homepage/tutoring/unknown_password', [TutoringController::class, 'unknownPassword'])->middleware('tool-licensed:Nachhilfetool');
    Route::post('/homepage/tutoring/login_with_token', [TutoringController::class, 'loginWithToken'])->middleware('tool-licensed:Nachhilfetool');
    Route::post('/homepage/tutoring/login_with_password', [TutoringController::class, 'loginWithPassword'])->middleware('tool-licensed:Nachhilfetool');
    Route::get('/homepage/tutoring/load_offer_config', [OfferController::class, 'loadOfferConfig']);
    Route::get('/homepage/tutoring/load_offers', [OfferController::class, 'loadOffers']);
    Route::post('/homepage/tutoring/click_count', [OfferController::class, 'clickCount']);
    Route::post('/homepage/tutoring/set_user_search_criteria', [OfferController::class, 'setUserSearchCriteria']);
    // setUserSearchCriteria

    // Public: returns a guest-safe "not impersonating" response when unauthenticated.
    Route::get('/admin/impersonation/status', [ImpersonationController::class, 'status']);

    /* SANCTUM */
    Route::middleware(['auth:sanctum'])->group(function () {
        // navigation, menus
        Route::get('/admin/navigation/profile_menu', [NavigationController::class, 'profileMenu']);
        Route::get('/admin/navigation/user_menu', [NavigationController::class, 'userMenu']);

        // users
        Route::apiResource('/admin/users', UserController::class)->names('admin.users');
        Route::post('/admin/impersonation/stop', [ImpersonationController::class, 'stop']);

        Route::get('/admin/health/status', [HealthController::class, 'status']);
        Route::get('/admin/health/test-queue', [HealthController::class, 'testQueue']);
        Route::get('/admin/health/test-queue/check', [HealthController::class, 'checkQueueTest']);

        // Notes API - Accessible to all authenticated users
        Route::apiResource('/homepage/notes', NoteController::class);
        Route::post('/homepage/notes/{note}/toggle-pin', [NoteController::class, 'togglePin']);
    });

    /* SANCTUM - aba_teacher */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:aba_teacher_access', 'tool-licensed:ABA,auth,scope:aba_teacher_access'])->group(function () {
        Route::get('/admin/aba/settings', [AbaSettingsController::class, 'index']);
        Route::get('/admin/aba/schoolyears', [SchoolyearController::class, 'index']);
        Route::post('/admin/aba/schoolyears/set_active', [SchoolyearController::class, 'setActiveSchoolyear']);

        // ABA CRUD
        Route::get('/admin/abas', [AbaController::class, 'index']);
        Route::post('/admin/abas', [AbaController::class, 'store']);
        Route::get('/admin/abas/{aba}', [AbaController::class, 'show']);
        Route::put('/admin/abas/{aba}', [AbaController::class, 'update']);
        Route::delete('/admin/abas/{aba}', [AbaController::class, 'destroy']);
        Route::get('/admin/abas/{aba}/extraction', [AbaExtractionController::class, 'show']);
        Route::post('/admin/abas/{aba}/extraction', [AbaExtractionController::class, 'store']);
        Route::get('/admin/abas/{aba}/extraction-runs/{run}/title-page-assets/{assetIndex}', [AbaExtractionController::class, 'titlePageAsset'])->whereNumber('assetIndex');
        Route::post('/admin/abas/{aba}/attachments/from-temp', [AbaAttachmentController::class, 'storeFromTemp']);
        Route::delete('/admin/abas/{aba}/attachments/{attachment}', [AbaAttachmentController::class, 'destroy']);

        // ABA chunk upload for FilePond
        Route::post('/admin/aba/uploads/chunk', [AbaChunkUploadController::class, 'upload']);
        Route::patch('/admin/aba/uploads/chunk', [AbaChunkUploadController::class, 'uploadNext']);
        Route::delete('/admin/aba/uploads/chunk/{upload_id}', [AbaChunkUploadController::class, 'destroy']);
    });

    /* SANCTUM - admin shell profile */
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::put('/admin/users/update_profile/{user}', [UserController::class, 'updateProfile']);
        Route::post('/admin/users/update_with_code', [UserController::class, 'updateWithCode']);
        Route::post('/admin/users/save_password', [UserController::class, 'savePassword']);
        Route::post('/admin/users/save_password_with_code', [UserController::class, 'savePasswordWithCode']);
        Route::post('/admin/users/save_2fa', [UserController::class, 'save2Fa']);
        Route::post('/admin/users/save_2fa_with_code', [UserController::class, 'save2FaWithCode']);
        Route::get('/admin/hopper_accounts', [UserHopperAccountController::class, 'index']);
        Route::post('/admin/hopper_accounts', [UserHopperAccountController::class, 'store']);
        Route::delete('/admin/hopper_accounts', [UserHopperAccountController::class, 'destroy']);
        Route::post('/admin/hopper_accounts/switch', [UserHopperAccountController::class, 'switch']);
        Route::post('/admin/hopper_accounts/load_switchable_schools', [UserHopperAccountController::class, 'loadSwitchableSchools']);
        Route::post('/admin/hopper_accounts/search_users', [UserHopperAccountController::class, 'searchUsers']);
    });

    /* SANCTUM - user */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:admin_user_profile_access'])->group(function () {
        // RegisterUsers
        Route::get('/admin/register_users', [RegisterUserController::class, 'index'])->middleware('tool-licensed:Anmeldetool,auto,scope:admin_user_profile_access');
        Route::post('/admin/register_users/delete_register_users', [RegisterUserController::class, 'deleteRegisterUsers'])->middleware('tool-licensed:Anmeldetool,auto,scope:admin_user_profile_access');
    });

    /* SANCTUM - admin */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:admin_access'])->group(function () {
        Route::get('/admin/users20/load_users', [UserController::class, 'loadUsers']);
        Route::post('/admin/users20/update', [UserController::class, 'updateUser']);
        Route::post('/admin/users20/store', [UserController::class, 'storeUser']);
        Route::post('/admin/users20/delete_users', [UserController::class, 'deleteUsers']);
        Route::post('/admin/users20/mark_account_status', [UserController::class, 'markAccountStatus']);

        // teachers, teachers_list
        Route::apiResource('/admin/teachers', TeacherController::class);
        Route::post('/admin/teachers/delete_teachers', [TeacherController::class, 'deleteTeachers']);
        Route::apiResource('/admin/teachers_list', TeachersListController::class);
        Route::post('/admin/teachers_list/delete_teachers', [TeachersListController::class, 'deleteTeachers']);

        Route::post('/admin/teachers_list_upload', [TeachersListController::class, 'upload']);
        Route::patch('/admin/teachers_list_upload', [TeachersListController::class, 'uploadNext']);

        // SchoolTool - Active Schoolyear
        Route::post('/admin/school_tools/set_active_schoolyear', [SchoolToolController::class, 'setActiveSchoolyear']);
    });

    /* SANCTUM - admin, teaching_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:teaching_upload_access', 'tool-licensed:Lehrertool,auto,scope:teaching_upload_access'])->group(function () {
        Route::post('/admin/teaching_upload/{slug}', [FileUploadController::class, 'upload']);
        Route::patch('/admin/teaching_upload/{slug}', [FileUploadController::class, 'uploadNext']);
    });

    /* SANCTUM - admin, lunch_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:restaurant_access', 'tool-licensed:Restaurant,auto,scope:restaurant_access'])->group(function () {
        Route::get('/admin/restaurant/settings', [RestaurantSettingsController::class, 'index']);
        Route::get('/admin/restaurant/cdgym/legacy-stats', RestaurantCdgymLegacyStatsController::class);
        Route::post('/admin/restaurant/cdgym/legacy-import', RestaurantCdgymLegacyImportController::class);
        Route::get('/admin/restaurant/users', [RestaurantUserController::class, 'index']);
        Route::get('/admin/restaurant/sepa-users', [RestaurantUserController::class, 'sepaUsers']);
        Route::get('/admin/restaurant/sepa-users/{flowUuid}/print', [RestaurantUserController::class, 'printSepaMandate']);
        Route::put('/admin/restaurant/users/{user}/confirm', [RestaurantUserController::class, 'confirm']);
        Route::delete('/admin/restaurant/users/{user}', [RestaurantUserController::class, 'destroy']);
        Route::put('/admin/restaurant/users/{user}/sepa', [RestaurantUserController::class, 'updateSepa']);
        Route::put('/admin/restaurant/general-settings', [RestaurantGeneralSettingsController::class, 'update']);
        Route::get('/admin/restaurant/sepa-settings/preview', [RestaurantSepaSettingsController::class, 'preview']);
        Route::put('/admin/restaurant/sepa-settings', [RestaurantSepaSettingsController::class, 'update']);
        Route::put('/admin/restaurant/online-settings', [RestaurantOnlineSettingsController::class, 'update']);
        Route::put('/admin/restaurant/user-settings', [RestaurantUserSettingsController::class, 'update']);
        Route::get('/admin/restaurant/free-days', [RestaurantFreeDayController::class, 'index']);
        Route::post('/admin/restaurant/free-days', [RestaurantFreeDayController::class, 'store']);
        Route::apiResource('/admin/restaurant/foods', RestaurantFoodController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('/admin/restaurant/menus', RestaurantMenuController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('/admin/restaurant/categories', RestaurantCategoryController::class)->only(['store', 'update', 'destroy']);
        Route::get('/admin/restaurant/ingredient_icons/private-directory', [RestaurantIngredientIconController::class, 'privateDirectory']);
        Route::post('/admin/restaurant/ingredient_icons/sync-private', [RestaurantIngredientIconController::class, 'syncFromPrivateDirectory']);
        Route::apiResource('/admin/restaurant/ingredient_icons', RestaurantIngredientIconController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('/admin/restaurant/eating-times', RestaurantEatingTimeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/admin/restaurant/billings/preview', [RestaurantBillingController::class, 'preview']);
        Route::get('/admin/restaurant/billings/{id}/print', [RestaurantBillingController::class, 'print']);
        Route::apiResource('/admin/restaurant/billings', RestaurantBillingController::class)->only(['index', 'store']);
        Route::get('/admin/restaurant/menu-plans/{id}/print', [RestaurantMenuPlanController::class, 'print']);
        Route::get('/admin/restaurant/menu-plans/{planId}/entries/{entryId}/booking-users', [RestaurantMenuPlanController::class, 'searchEntryBookingUsers']);
        Route::get('/admin/restaurant/menu-plans/{planId}/entries/{entryId}/bookings', [RestaurantMenuPlanController::class, 'entryBookings']);
        Route::post('/admin/restaurant/menu-plans/{planId}/entries/{entryId}/bookings', [RestaurantMenuPlanController::class, 'storeEntryBooking']);
        Route::delete('/admin/restaurant/menu-plans/{planId}/entries/{entryId}/bookings/{bookingId}', [RestaurantMenuPlanController::class, 'destroyEntryBooking']);
        Route::post('/admin/restaurant/menu-plans/{id}/toggle-lock', [RestaurantMenuPlanController::class, 'toggleLock']);
        Route::apiResource('/admin/restaurant/menu-plans', RestaurantMenuPlanController::class);
    });

    /* SANCTUM - tutoring_user */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:tutoring_user_access', 'tool-licensed:Nachhilfetool,auto,scope:tutoring_user_access'])->group(function () {
        Route::apiResource('/homepage/tutoring/users', App\Http\Controllers\Tutoring\UserController::class)->names('tutoring.users');
        Route::post('/homepage/tutoring/update_password', [App\Http\Controllers\Tutoring\UserController::class, 'updatePassword']);
        Route::post('/homepage/tutoring/logout', [App\Http\Controllers\Tutoring\UserController::class, 'logout']);
        Route::get('/homepage/tutoring/load_auth', [TutoringController::class, 'loadAuth']);
        Route::get('/homepage/tutoring/load_my_offers', [OfferController::class, 'loadMyOffers']);
        Route::apiResource('/homepage/tutoring/subjects', SubjectController::class)->names('tutoring.subjects');
        Route::apiResource('/homepage/tutoring/offers', OfferController::class)->names('tutoring.offers');
        Route::post('/homepage/tutoring/toggle_offer', [OfferController::class, 'toggleOffer']);
        Route::post('/homepage/tutoring/send_request', [OfferController::class, 'sendRequest']);
        Route::apiResource('/homepage/tutoring/offer_requests', OfferRequestController::class)->names('tutoring.offer_requests');
        Route::get('/homepage/tutoring/received_offer_requests', [OfferRequestController::class, 'receivedRequests']);
        Route::post('/homepage/tutoring/request_mail_clicked', [OfferRequestController::class, 'requestMailClicked']);
        Route::post('/homepage/tutoring/to_archive', [OfferRequestController::class, 'toArchive']);
        Route::post('/homepage/tutoring/to_active', [OfferRequestController::class, 'toActive']);
        Route::post('/homepage/tutoring/to_user_archive', [OfferRequestController::class, 'toUserArchive']);
        Route::post('/homepage/tutoring/to_user_active', [OfferRequestController::class, 'toUserActive']);

        // api/homepage/tutoring/offer_requests
    });

    /* SANCTUM - admin, tutoring_admin, register_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:school_tool_access'])->group(function () {
        Route::get('/admin/school_tools/load_config', [SchoolToolController::class, 'loadConfig']);
        Route::post('/admin/users20/toggle_is_active', [UserController::class, 'toggleIsActive']);
    });

    Route::middleware(['auth:sanctum', 'api-allowed:scope:admin_or_super_admin_access'])->group(function () {
        Route::post('/admin/school_tools/save_module_statuses', [SchoolToolController::class, 'saveModuleStatuses']);
    });

    /* SANCTUM - admin, materials_admin, materials_moderator */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:materials_access'])->group(function () {
        Route::apiResource('/admin/groups', GroupController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/admin/groups/{group}/members', [GroupController::class, 'members']);
        Route::get('/admin/groups/{group}/source-members', [GroupController::class, 'sourceMembers']);
        Route::delete('/admin/groups/{group}/members/{member}', [GroupController::class, 'removeMember']);
        Route::post('/admin/groups/{group}/remove-users', [GroupController::class, 'removeMembers']);
        Route::get('/admin/groups/{group}/assignable-users', [GroupController::class, 'assignableUsers']);
        Route::post('/admin/groups/{group}/assign-users', [GroupController::class, 'assignUsers']);
        Route::get('/admin/groups/{group}/assignable-groups', [GroupController::class, 'assignableGroups']);
        Route::post('/admin/groups/{group}/assign-from-group', [GroupController::class, 'assignFromGroup']);
        Route::get('/admin/groups/{group}/my-teaching-courses', [GroupController::class, 'myTeachingCourses']);
    });

    /* SANCTUM - admin, tutoring_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:tutoring_admin_access'])->group(function () {});

    /* SANCTUM - admin, tutoring_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:tutoring_admin_access', 'tool-licensed:Nachhilfetool,auto,scope:tutoring_admin_access'])->group(function () {
        Route::post('/admin/school_tools/save_tutoring_settings', [SchoolToolController::class, 'saveTutoringSettings']);
        Route::apiResource('/admin/tutoring/subjects', App\Http\Controllers\Admin\Tutoring\SubjectController::class)->names('admin.tutoring.subjects');
        Route::apiResource('/admin/tutoring/users', App\Http\Controllers\Admin\Tutoring\UserController::class)->names('admin.tutoring.users');
        Route::post('/admin/tutoring/create_subjects', [App\Http\Controllers\Admin\Tutoring\SubjectController::class, 'createSubjects']);
        Route::post('/admin/tutoring/delete_users', [App\Http\Controllers\Admin\Tutoring\UserController::class, 'deleteUsers']);
        Route::post('/admin/tutoring/clean_users', [App\Http\Controllers\Admin\Tutoring\UserController::class, 'cleanUsers']);
        Route::post('/admin/tutoring/confirm_users', [App\Http\Controllers\Admin\Tutoring\UserController::class, 'confirmUsers']);
    });

    /* SANCTUM - admin, teaching_admin, teacher */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:teaching_access', 'tool-licensed:Lehrertool,auto,scope:teaching_access'])->group(function () {
        Route::get('/admin/teaching/search116', [TeachingController::class, 'search116']);
        Route::get('/admin/teaching/load_settings', [TeachingController::class, 'loadSettings']);
        Route::post('/admin/teaching/save_settings', [TeachingController::class, 'saveSettings']);
        Route::post('/admin/teaching/import_behaviour', [TeachingController::class, 'importBehaviour']);
        Route::post('/admin/teaching/reset_behaviour', [TeachingController::class, 'resetBehaviour']);
        Route::post('/admin/teaching/import_notifications', [TeachingController::class, 'importNotifications']);
        Route::post('/admin/teaching/reset_notifications', [TeachingController::class, 'resetNotifications']);
        Route::post('/admin/teaching/import_schema', [TeachingController::class, 'importSchema']);
        Route::post('/admin/teaching/reset_schema', [TeachingController::class, 'resetSchema']);
        Route::post('/admin/teaching/save_active_semester', [TeachingController::class, 'saveActiveSemester']);
        Route::post('/admin/teaching/save_semester_2_date', [TeachingController::class, 'saveSemester2Date']);
        Route::get('/admin/teaching/backups', [TeachingBackupController::class, 'index']);
        Route::post('/admin/teaching/backups', [TeachingBackupController::class, 'store']);
        Route::post('/admin/teaching/backups/import', [TeachingBackupController::class, 'import']);
        Route::get('/admin/teaching/backups/restore-runs', [TeachingBackupController::class, 'restoreRuns']);
        Route::get('/admin/teaching/backups/{backup}/preview', [TeachingBackupController::class, 'preview']);
        Route::post('/admin/teaching/backups/{backup}/restore', [TeachingBackupController::class, 'restore']);
        Route::post('/admin/teaching/backups/{backup}/restore-full', [TeachingBackupController::class, 'restoreFull']);
        Route::delete('/admin/teaching/backups/{backup}', [TeachingBackupController::class, 'destroy']);
        Route::get('/admin/teaching/backups/{backup}/download', [TeachingBackupController::class, 'download']);
        Route::get('/admin/teaching/courses/{course}/performances_pdf', [TeachingCourseController::class, 'coursePerformancesPdf']);
        Route::get('/admin/teaching/courses/{course}/grades_pdf', [TeachingCourseController::class, 'courseGradesPdf']);
        Route::get('/admin/teaching/courses/{course}/students/{course_student}/performances_pdf', [TeachingCourseController::class, 'studentPerformancesPdf']);
        Route::apiResource('/admin/teaching/courses', TeachingCourseController::class);
        Route::get('/admin/teaching/curricula/free-weeks-template', [CurriculumController::class, 'freeWeeksTemplate']);
        Route::put('/admin/teaching/curricula/free-weeks-template', [CurriculumController::class, 'updateFreeWeeksTemplate']);
        Route::apiResource('/admin/teaching/curricula', CurriculumController::class)->parameters(['curricula' => 'curriculum']);
        Route::get('/admin/teaching/imported-curricula', [ImportedCurriculumController::class, 'index']);
        Route::post('/admin/teaching/imported-curricula/import', [ImportedCurriculumController::class, 'import']);
        Route::post('/admin/teaching/imported-curricula/{imported_curriculum}/adopt', [ImportedCurriculumController::class, 'adopt']);
        Route::delete('/admin/teaching/imported-curricula/{imported_curriculum}', [ImportedCurriculumController::class, 'destroy']);
        Route::get('/admin/teaching/curricula/{curriculum}/materials/config', [CurriculumController::class, 'materialsConfig']);
        Route::get('/admin/teaching/curricula/{curriculum}/materials/cards', [CurriculumController::class, 'materialsIndex']);
        Route::get('/admin/teaching/curricula/{curriculum}/materials/cards/{material_card}', [CurriculumController::class, 'showMaterialCard']);
        Route::get('/admin/teaching/curricula/{curriculum}/materials/attachments/{material_card_attachment}/preview', [CurriculumController::class, 'previewMaterialAttachment']);
        Route::get('/admin/teaching/curricula/{curriculum}/materials/attachments/{material_card_attachment}/download', [CurriculumController::class, 'downloadMaterialAttachment']);
        Route::get('/admin/teaching/curricula/{curriculum}/documents', [CurriculumDocumentController::class, 'index']);
        Route::post('/admin/teaching/curricula/{curriculum}/documents/upload', [CurriculumDocumentController::class, 'upload']);
        Route::patch('/admin/teaching/curricula/{curriculum}/documents/upload', [CurriculumDocumentController::class, 'uploadNext']);
        Route::post('/admin/teaching/curricula/{curriculum}/documents/attach-material', [CurriculumDocumentController::class, 'attachMaterial']);
        Route::get('/admin/teaching/curricula/{curriculum}/documents/{document}/preview', [CurriculumDocumentController::class, 'preview']);
        Route::get('/admin/teaching/curricula/{curriculum}/documents/{document}/material-attachments', [CurriculumDocumentController::class, 'materialAttachments']);
        Route::patch('/admin/teaching/curricula/{curriculum}/documents/{document}/material-attachment', [CurriculumDocumentController::class, 'updateMaterialAttachment']);
        Route::get('/admin/teaching/curricula/{curriculum}/documents/{document}/download', [CurriculumDocumentController::class, 'download']);
        Route::delete('/admin/teaching/curricula/{curriculum}/documents/{document}', [CurriculumDocumentController::class, 'destroy']);
        Route::get('/admin/teaching/curricula/{curriculum}/export/json', [CurriculumExportController::class, 'json']);
        Route::get('/admin/teaching/curricula/{curriculum}/export/word', [CurriculumExportController::class, 'word']);
        Route::get('/admin/teaching/curricula/{curriculum}/export/pdf', [CurriculumExportController::class, 'pdf']);
        Route::apiResource('/admin/teaching/course_dates', CourseDateController::class);
        Route::patch('/admin/teaching/course_dates/{course_date}/status', [CourseDateController::class, 'updateStatus']);
        Route::post('/admin/teaching/course_dates/{course_date}/adopt-curriculum-content', [CourseDateController::class, 'adoptCurriculumContent']);
        Route::post('/admin/teaching/course_date_materials/attachments/{attachment}/toggle-visibility', [CourseDateController::class, 'toggleAttachmentVisibility']);
        Route::delete('/admin/teaching/course_date_materials/{material}', [CourseDateController::class, 'destroyAdoptedMaterial']);
        Route::get('/admin/teaching/course_date_materials/attachments/{attachment}/preview', [CourseDateController::class, 'previewAdoptedAttachment']);
        Route::get('/admin/teaching/course_date_materials/attachments/{attachment}/download', [CourseDateController::class, 'downloadAdoptedAttachment']);
        Route::apiResource('/admin/teaching/holidays', HolidayController::class)->only(['index', 'store', 'destroy']);
        Route::apiResource('/admin/teaching/school_hours', SchoolHourController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('/admin/teaching/my_holidays', MyHolidayController::class)
            ->only(['index', 'store', 'destroy'])
            ->parameters(['my_holidays' => 'my_holiday']);
        Route::get('/admin/teaching/load_class_students', [App\Http\Controllers\Admin\Teaching\StudentController::class, 'loadClassStudents']);
        Route::get('/admin/teaching/import116/load_class_students', [Import116Controller::class, 'loadClassStudents']);
        Route::get('/admin/teaching/import116/runs', [Import116Controller::class, 'runs']);
        Route::get('/admin/teaching/import116/runs/{import116_run}', [Import116Controller::class, 'runDetails']);
        Route::post('/admin/teaching/import116/runs/reset', [Import116Controller::class, 'resetRuns']);
        Route::delete('/admin/teaching/import116/runs/{import116_run}', [Import116Controller::class, 'destroyRun']);
        Route::apiResource('/admin/teaching/course_works', CourseWorkController::class);
        Route::apiResource('/admin/teaching/course_student_entries', App\Http\Controllers\Admin\Teaching\CourseStudentEntryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('/admin/teaching/course_student_category_evaluations', CourseStudentCategoryEvaluationController::class)->only(['index', 'store']);
        Route::apiResource('/admin/teaching/course_behaviour_entries', CourseBehaviourEntryController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::get('/admin/materials/storage-audit', [MaterialStorageAuditController::class, 'show'])->middleware(['auth:sanctum']);
    Route::post('/admin/materials/storage-audit/start', [MaterialStorageAuditController::class, 'startAudit'])->middleware(['auth:sanctum']);
    Route::get('/admin/materials/storage-audit/operations/{operationId}', [MaterialStorageAuditController::class, 'auditStatus'])->middleware(['auth:sanctum']);
    Route::post('/admin/materials/storage-audit/purge', [MaterialStorageAuditController::class, 'purge'])->middleware(['auth:sanctum']);
    Route::post('/admin/materials/storage-audit/sync-local', [MaterialStorageAuditController::class, 'syncLocal'])->middleware(['auth:sanctum']);
    Route::get('/admin/materials/storage-audit/sync-operations/{operationId}', [MaterialStorageAuditController::class, 'syncStatus'])->middleware(['auth:sanctum']);
    Route::delete('/admin/materials/storage-audit/database-only-materials', [MaterialStorageAuditController::class, 'destroyDatabaseOnlyMaterials'])->middleware(['auth:sanctum']);
    Route::delete('/admin/materials/storage-audit/database-only-attachments/{attachmentId}', [MaterialStorageAuditController::class, 'destroyDatabaseOnlyAttachment'])->middleware(['auth:sanctum']);

    /* SANCTUM - admin, materials_admin, materials_moderator */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:materials_access', 'tool-licensed:Materialientool,auto,scope:materials_access'])->group(function () {
        Route::get('/admin/materials/config', [MaterialController::class, 'config']);
        Route::post('/admin/materials/workspaces', [MaterialWorkspaceController::class, 'store']);
        Route::put('/admin/materials/workspaces/{material_workspace}', [MaterialWorkspaceController::class, 'update']);
        Route::delete('/admin/materials/workspaces/{material_workspace}', [MaterialWorkspaceController::class, 'destroy']);
        Route::post('/admin/materials/subjects', [MaterialClassificationController::class, 'storeSubject']);
        Route::put('/admin/materials/subjects/{material_subject}', [MaterialClassificationController::class, 'updateSubject']);
        Route::delete('/admin/materials/subjects/{material_subject}', [MaterialClassificationController::class, 'destroySubject']);
        Route::post('/admin/materials/subjects/{material_subject}/move', [MaterialClassificationController::class, 'moveSubject']);
        Route::post('/admin/materials/subjects/{material_subject}/convert-to-topic', [MaterialClassificationController::class, 'convertSubjectToTopic']);
        Route::post('/admin/materials/topics', [MaterialClassificationController::class, 'storeTopic']);
        Route::put('/admin/materials/topics/{material_topic}', [MaterialClassificationController::class, 'updateTopic']);
        Route::delete('/admin/materials/topics/{material_topic}', [MaterialClassificationController::class, 'destroyTopic']);
        Route::post('/admin/materials/topics/{material_topic}/move', [MaterialClassificationController::class, 'moveTopic']);
        Route::post('/admin/materials/topics/{material_topic}/unlink', [MaterialController::class, 'unlinkTopic']);
        Route::post('/admin/materials/topics/{material_topic}/move-to-subject', [MaterialClassificationController::class, 'moveTopicToSubject']);
        Route::post('/admin/materials/topics/{material_topic}/convert-to-subject', [MaterialClassificationController::class, 'convertTopicToSubject']);
        Route::post('/admin/materials/topics/{material_topic}/convert-to-unit', [MaterialClassificationController::class, 'convertTopicToUnit']);
        Route::post('/admin/materials/units', [MaterialClassificationController::class, 'storeUnit']);
        Route::put('/admin/materials/units/{material_unit}', [MaterialClassificationController::class, 'updateUnit']);
        Route::delete('/admin/materials/units/{material_unit}', [MaterialClassificationController::class, 'destroyUnit']);
        Route::post('/admin/materials/units/{material_unit}/move', [MaterialClassificationController::class, 'moveUnit']);
        Route::post('/admin/materials/units/{material_unit}/move-to-topic', [MaterialClassificationController::class, 'moveUnitToTopic']);
        Route::post('/admin/materials/units/{material_unit}/convert-to-topic', [MaterialClassificationController::class, 'convertUnitToTopic']);
        Route::post('/admin/materials/units/{material_unit}/unlink', [MaterialController::class, 'unlinkUnit']);
        Route::put('/admin/materials/user-settings', [MaterialUserSettingsController::class, 'update']);
        Route::get('/admin/materials/cards', [MaterialController::class, 'index']);
        Route::post('/admin/materials/cards', [MaterialController::class, 'store']);
        Route::post('/admin/materials/cards/quick_store', [MaterialController::class, 'quickStore']);
        Route::get('/admin/materials/cards/deleted-restore-list', [MaterialController::class, 'deletedRestoreList']);
        Route::get('/admin/materials/deleted-restore-list', [MaterialController::class, 'deletedWorkspaceRestoreList']);
        Route::get('/admin/materials/cards/last-deleted-restore-info', [MaterialController::class, 'lastDeletedRestoreInfo']);
        Route::post('/admin/materials/cards/restore-last-deleted', [MaterialController::class, 'restoreLastDeleted']);
        Route::post('/admin/materials/cards/restore-deleted/{card_id}', [MaterialController::class, 'restoreDeletedById'])->whereNumber('card_id');
        Route::post('/admin/materials/restore-deleted', [MaterialController::class, 'restoreDeletedWorkspaceItem']);
        Route::delete('/admin/materials/cards/deleted/{card_id}', [MaterialController::class, 'purgeDeletedById'])->whereNumber('card_id');
        Route::delete('/admin/materials/deleted', [MaterialController::class, 'purgeDeletedWorkspaceItem']);
        Route::get('/admin/materials/cards/{material_card}', [MaterialController::class, 'show']);
        Route::put('/admin/materials/cards/{material_card}', [MaterialController::class, 'update']);
        Route::post('/admin/materials/cards/{material_card}/unlink', [MaterialController::class, 'unlink']);
        Route::delete('/admin/materials/cards/{material_card}', [MaterialController::class, 'destroy']);
        Route::post('/admin/materials/cards/{material_card}/attachments/link', [MaterialController::class, 'storeLinkAttachment']);
        Route::post('/admin/materials/cards/{material_card}/attachments/image-url', [MaterialController::class, 'storeRemoteImageAttachment']);
        Route::post('/admin/materials/cards/{material_card}/attachments/file', [MaterialController::class, 'storeFileAttachment']);
        Route::post('/admin/materials/cards/{material_card}/attachments/file-temp', [MaterialController::class, 'storeTempFileAttachment']);
        Route::patch('/admin/materials/attachments/{material_card_attachment}', [MaterialController::class, 'updateAttachment']);
        Route::get('/admin/materials/attachments/{material_card_attachment}/text-content', [MaterialController::class, 'textAttachmentContent']);
        Route::patch('/admin/materials/attachments/{material_card_attachment}/text-content', [MaterialController::class, 'updateTextAttachmentContent']);
        Route::delete('/admin/materials/attachments/{material_card_attachment}', [MaterialController::class, 'destroyAttachment']);
        Route::get('/admin/materials/attachments/{material_card_attachment}/preview', [MaterialController::class, 'previewAttachment']);
        Route::get('/admin/materials/attachments/{material_card_attachment}/download', [MaterialController::class, 'downloadAttachment']);
        Route::get('/admin/materials/attachments/{material_card_attachment}/download-docx', [MaterialController::class, 'downloadAttachmentDocx']);
        Route::get('/admin/materials/shares', [MaterialShareController::class, 'index']);
        Route::patch('/admin/materials/shares/{material_share_rule}', [MaterialShareController::class, 'updateRule']);
        Route::post('/admin/materials/shares/targets', [MaterialShareController::class, 'storeTarget']);
        Route::patch('/admin/materials/shares/targets/{material_share_target}', [MaterialShareController::class, 'updateTarget']);
        Route::delete('/admin/materials/shares/targets/{material_share_target}', [MaterialShareController::class, 'destroyTarget']);
        Route::get('/admin/materials/shares/lookup-users', [MaterialShareController::class, 'lookupUsers']);
        Route::get('/admin/materials/shares/lookup-groups', [MaterialShareController::class, 'lookupGroups']);
        Route::get('/admin/materials/shares/lookup-group-members', [MaterialShareController::class, 'lookupGroupMembers']);
        Route::get('/admin/materials/shares/lookup-schools', [MaterialShareController::class, 'lookupSchools']);
        Route::get('/admin/materials/shares/lookup-external-user', [MaterialShareController::class, 'lookupExternalUser']);
        Route::get('/admin/materials/shares/inbox-users', [MaterialShareController::class, 'inboxUsers']);
        Route::get('/admin/materials/shares/inbox/material-attachments', [MaterialShareController::class, 'inboxMaterialAttachments']);
        Route::get('/admin/materials/shares/inbox/material-detail', [MaterialShareController::class, 'inboxMaterialDetail']);
        Route::put('/admin/materials/shares/inbox/material-detail', [MaterialShareController::class, 'updateInboxMaterialDetail']);
        Route::delete('/admin/materials/shares/inbox/material-detail', [MaterialShareController::class, 'destroyInboxMaterialDetail']);
        Route::post('/admin/materials/shares/inbox/subjects', [MaterialShareController::class, 'storeInboxSubject']);
        Route::post('/admin/materials/shares/inbox/topics', [MaterialShareController::class, 'storeInboxTopic']);
        Route::post('/admin/materials/shares/inbox/units', [MaterialShareController::class, 'storeInboxUnit']);
        Route::post('/admin/materials/shares/inbox/subjects/{material_subject}/move', [MaterialShareController::class, 'moveInboxSubject']);
        Route::post('/admin/materials/shares/inbox/topics/{material_topic}/move', [MaterialShareController::class, 'moveInboxTopic']);
        Route::post('/admin/materials/shares/inbox/units/{material_unit}/move', [MaterialShareController::class, 'moveInboxUnit']);
        Route::post('/admin/materials/shares/inbox/workspaces/{material_share_rule}/insert-tree', [MaterialShareController::class, 'insertInboxWorkspaceTree']);
        Route::post('/admin/materials/shares/inbox/subjects/{material_subject}/insert-tree', [MaterialShareController::class, 'insertInboxSubjectTree']);
        Route::post('/admin/materials/shares/inbox/topics/{material_topic}/insert-tree', [MaterialShareController::class, 'insertInboxTopicTree']);
        Route::post('/admin/materials/shares/inbox/units/{material_unit}/insert-tree', [MaterialShareController::class, 'insertInboxUnitTree']);
        Route::get('/admin/materials/shares/inbox/import-operations/{operationId}', [MaterialShareController::class, 'inboxInsertOperationStatus']);
        Route::put('/admin/materials/shares/inbox/subjects/{material_subject}', [MaterialShareController::class, 'updateInboxSubject']);
        Route::put('/admin/materials/shares/inbox/topics/{material_topic}', [MaterialShareController::class, 'updateInboxTopic']);
        Route::put('/admin/materials/shares/inbox/units/{material_unit}', [MaterialShareController::class, 'updateInboxUnit']);
        Route::post('/admin/materials/shares/inbox/subjects/{material_subject}/materials', [MaterialShareController::class, 'storeInboxSubjectMaterial']);
        Route::post('/admin/materials/shares/inbox/topics/{material_topic}/materials', [MaterialShareController::class, 'storeInboxTopicMaterial']);
        Route::post('/admin/materials/shares/inbox/units/{material_unit}/materials', [MaterialShareController::class, 'storeInboxUnitMaterial']);
        Route::delete('/admin/materials/shares/inbox/subjects/{material_subject}', [MaterialShareController::class, 'destroyInboxSubject']);
        Route::delete('/admin/materials/shares/inbox/topics/{material_topic}', [MaterialShareController::class, 'destroyInboxTopic']);
        Route::delete('/admin/materials/shares/inbox/units/{material_unit}', [MaterialShareController::class, 'destroyInboxUnit']);
        Route::get('/admin/materials/shares/inbox/deleted-restore-list', [MaterialShareController::class, 'deletedInboxRestoreList']);
        Route::post('/admin/materials/shares/inbox/restore-deleted', [MaterialShareController::class, 'restoreDeletedInboxItem']);
        Route::delete('/admin/materials/shares/inbox/deleted', [MaterialShareController::class, 'purgeDeletedInboxItem']);
        Route::post('/admin/materials/shares/inbox/material-attachments/link', [MaterialShareController::class, 'storeInboxLinkAttachment']);
        Route::post('/admin/materials/shares/inbox/material-attachments/image-url', [MaterialShareController::class, 'storeInboxRemoteImageAttachment']);
        Route::post('/admin/materials/shares/inbox/material-attachments/file', [MaterialShareController::class, 'storeInboxFileAttachment']);
        Route::post('/admin/materials/shares/inbox/material-attachments/file-temp', [MaterialShareController::class, 'storeInboxTempFileAttachment']);
        Route::patch('/admin/materials/shares/inbox/material-attachments/{material_card_attachment}', [MaterialShareController::class, 'updateInboxAttachment']);
        Route::delete('/admin/materials/shares/inbox/material-attachments/{material_card_attachment}', [MaterialShareController::class, 'destroyInboxAttachment']);
        Route::get('/admin/materials/shares/inbox/material-attachments/{material_card_attachment}/text-content', [MaterialShareController::class, 'inboxTextAttachmentContent']);
        Route::patch('/admin/materials/shares/inbox/material-attachments/{material_card_attachment}/text-content', [MaterialShareController::class, 'updateInboxTextAttachmentContent']);
        Route::post('/admin/materials/shares/inbox/archive', [MaterialShareController::class, 'archiveInboxRule']);
        Route::post('/admin/materials/shares/inbox/unarchive', [MaterialShareController::class, 'unarchiveInboxRule']);
        Route::post('/admin/materials/shares/inbox/material-original-copy', [MaterialShareController::class, 'copyInboxMaterialAsOriginal']);
        Route::post('/admin/materials/shares/inbox/material-insert', [MaterialShareController::class, 'insertInboxMaterial']);
        Route::post('/admin/materials/uploads/chunk', [MaterialChunkUploadController::class, 'upload']);
        Route::patch('/admin/materials/uploads/chunk', [MaterialChunkUploadController::class, 'uploadNext']);
        Route::delete('/admin/materials/uploads/chunk/{upload_id}', [MaterialChunkUploadController::class, 'destroy']);
    });

    /* SANCTUM - admin, materials_admin, materials_moderator */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:materials_access', 'tool-licensed:Materialientool,auto,scope:materials_access'])->group(function () {
        Route::get('/admin/materials/types', [MaterialTypeController::class, 'index']);
        Route::post('/admin/materials/types', [MaterialTypeController::class, 'store']);
        Route::put('/admin/materials/types/{material_type}', [MaterialTypeController::class, 'update']);
        Route::delete('/admin/materials/types/{material_type}', [MaterialTypeController::class, 'destroy']);
    });

    /* SANCTUM - admin */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:admin_access', 'tool-licensed:Materialientool,auto,scope:admin_access'])->group(function () {
        Route::get('/admin/materials/statuses', [MaterialStatusController::class, 'index']);
        Route::post('/admin/materials/statuses', [MaterialStatusController::class, 'store']);
        Route::put('/admin/materials/statuses/{material_status}', [MaterialStatusController::class, 'update']);
        Route::delete('/admin/materials/statuses/{material_status}', [MaterialStatusController::class, 'destroy']);
        Route::put('/admin/materials/file-settings', [MaterialFileSettingsController::class, 'update']);
    });

    /* SANCTUM - schoolyear selectors */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:schoolyear_access'])->group(function () {
        Route::apiResource('/admin/schoolyears', SchoolyearController::class)->only(['index']);
        Route::post('/admin/schoolyears/set_active', [SchoolyearController::class, 'setActiveSchoolyear']);
    });

    /* SANCTUM - admin, register_admin, tutoring_admin, teaching_admin, materials_admin, materials_moderator, teacher */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:staff_admin_access'])->group(function () {

        // Tutoring, Offers
        Route::apiResource('/admin/tutoring/offers', App\Http\Controllers\Admin\Tutoring\OfferController::class)->names('admin.tutoring.offers')->middleware('tool-licensed:Nachhilfetool,auto,scope:staff_admin_access');
        Route::post('/admin/tutoring/delete_offers', [App\Http\Controllers\Admin\Tutoring\OfferController::class, 'deleteOffers'])->middleware('tool-licensed:Nachhilfetool,auto,scope:staff_admin_access');
        Route::post('/admin/tutoring/toggle_active_offer', [App\Http\Controllers\Admin\Tutoring\OfferController::class, 'toggleActiveOffer'])->middleware('tool-licensed:Nachhilfetool,auto,scope:staff_admin_access');
        Route::post('/admin/tutoring/toggle_accepted_offer', [App\Http\Controllers\Admin\Tutoring\OfferController::class, 'toggleAcceptedOffer'])->middleware('tool-licensed:Nachhilfetool,auto,scope:staff_admin_access');
        Route::get('/admin/tutoring/get_stats', [App\Http\Controllers\Admin\Tutoring\OfferController::class, 'getStats'])->middleware('tool-licensed:Nachhilfetool,auto,scope:staff_admin_access');
        Route::get('/admin/tutoring/requests', [App\Http\Controllers\Admin\Tutoring\OfferRequestController::class, 'index'])->middleware('tool-licensed:Nachhilfetool,auto,scope:staff_admin_access');

        // Roles
        Route::get('/admin/roles/load_roles', [RoleController::class, 'loadRoles']);

        // licences
        Route::apiResource('/admin/licences', LicenceController::class);
        Route::post('/admin/licences/load_licences', [LicenceController::class, 'loadLicences']);
        Route::post('/admin/licences/delete_licences', [LicenceController::class, 'deleteLicences']);
        Route::put('/admin/licences/{licence}/save_licence_model', [LicenceController::class, 'saveLicenceModel']);

        // schools
        Route::apiResource('/admin/schools', SchoolController::class);
        Route::post('/admin/schools_upload/uploadLogo', [SchoolController::class, 'uploadLogo']);
        Route::patch('/admin/schools_upload/uploadLogo', [SchoolController::class, 'uploadLogoNext']);
        Route::post('/admin/schools/delete_schools', [SchoolController::class, 'deleteSchools']);
        Route::post('/admin/schools/load_switchable_schools', [SchoolController::class, 'loadSwitchableSchools']);
        Route::post('/admin/schools/search_switch_users', [SchoolController::class, 'searchSwitchUsers']);
        Route::post('/admin/schools/switch_school', [SchoolController::class, 'switchSchool']);
        Route::post('/admin/schools/add_licence', [SchoolController::class, 'addLicence']);
        Route::post('/admin/schools/delete_licence', [SchoolController::class, 'deleteLicence']);
        Route::put('/admin/school_licences/{school_licence}/save_school', [SchoolController::class, 'saveSchoolLicenceSchool']);
        Route::put('/admin/school_licences/{school_licence}/save_licence_model', [SchoolController::class, 'saveSchoolLicenceModel']);
        Route::get('/admin/school_licences/{school_licence}/users', [SchoolController::class, 'loadSchoolLicenceUsers']);
        Route::get('/admin/school_licences/{school_licence}/users/{user}/roles', [SchoolController::class, 'loadSchoolLicenceUserRoles']);
        Route::put('/admin/school_licences/{school_licence}/users/{user}/roles', [SchoolController::class, 'saveSchoolLicenceUserRoles']);
        Route::put('/admin/school_licences/{school_licence}/users/{user}/spatie_roles', [SchoolController::class, 'saveSchoolLicenceUserSpatieRoles']);
        Route::post('/admin/school_licences/{school_licence}/activate_user_licence', [SchoolController::class, 'activateCurrentUserLicence']);
        Route::post('/admin/school_licences/{school_licence}/renew_user_licence', [SchoolController::class, 'renewCurrentUserLicence']);
        Route::post('/admin/school_licences/{school_licence}/deactivate_user_licence', [SchoolController::class, 'deactivateCurrentUserLicence']);
        Route::post('/admin/schools/add_admin', [SchoolController::class, 'addAdmin']);
        Route::post('/admin/schools/delete_admin', [SchoolController::class, 'deleteAdmin']);

        // schoolyears
        Route::apiResource('/admin/schoolyears', SchoolyearController::class)->except(['index']);
        Route::get('/admin/schoolyears_paginate', [SchoolyearController::class, 'indexPaginate']);

        // registers
        Route::apiResource('/admin/registers', App\Http\Controllers\Admin\RegisterController::class)->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/registers/set_active', [App\Http\Controllers\Admin\RegisterController::class, 'setActiveRegister'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/registers/get_active', [App\Http\Controllers\Admin\RegisterController::class, 'getActiveRegisters'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/registers/toggle', [App\Http\Controllers\Admin\RegisterController::class, 'toggleRegister'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/registers/print_excel', [RegisterPrintController::class, 'printExcel'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/registers/print_supervisor', [RegisterPrintController::class, 'printSupervisor'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/registers/print_date', [RegisterPrintController::class, 'printDate'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');

        // register_dates
        Route::apiResource('/admin/register_dates', RegisterDateController::class)->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/register_dates/create_dates', [RegisterDateController::class, 'createDates'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/register_dates/load_days', [RegisterDateController::class, 'loadDays'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/register_dates/filter_register_dates', [RegisterDateController::class, 'filterRegisterDates'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/register_dates/lock_register_dates', [RegisterDateController::class, 'lockRegisterDates'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/register_dates/unlock_register_dates', [RegisterDateController::class, 'unlockRegisterDates'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/register_dates/delete_register_dates', [RegisterDateController::class, 'deleteRegisterDates'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');

        // register_date_bookings
        Route::apiResource('/admin/register_date_bookings', RegisterDateBookingController::class)->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/register_date_bookings/get_user_with_email', [RegisterDateBookingController::class, 'getUserWithEmail'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/register_date_bookings/update_or_create_user', [RegisterDateBookingController::class, 'updateOrCreateUser'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
        Route::post('/admin/register_date_bookings/delete_bookings', [RegisterDateBookingController::class, 'deleteBookings'])->middleware('tool-licensed:Anmeldetool,auto,scope:staff_admin_access');
    });

    /* SANCTUM - admin, register_admin, tutoring_admin, teaching_admin, materials_admin, materials_moderator, teacher, lunch_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:admin_shell_access'])->group(function () {
        Route::post('/admin/schools/load_school_infos', [SchoolController::class, 'loadSchoolInfos']);
    });

    /* SANCTUM - admin */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:admin_access'])->group(function () {

        // users
        Route::post('/admin/users/destroy_multiple', [UserController::class, 'destroyMultiple']);
        Route::post('/admin/users/send_verification_email', [UserController::class, 'sendVerificationEmail']);
        Route::post('/admin/users/confirm', [UserController::class, 'confirm']);
        Route::post('/admin/users/save_user_roles', [UserController::class, 'saveUserRoles']);

        // roles
        Route::apiResource('/admin/roles', SpaRoleController::class);
        Route::post('/admin/roles/destroy_multiple', [SpaRoleController::class, 'destroyMultiple']);
        Route::post('/admin/load_roles', [AdminController::class, 'loadRoles']);

        // users_with_roles
        Route::get('/admin/users_with_roles/roles', [UserWithRoleController::class, 'roles']);
        Route::post('/admin/users_with_roles/roles', [UserWithRoleController::class, 'saveUserRoles']);
        Route::apiResource('/admin/users_with_roles', UserWithRoleController::class);
    });

    /* SANCTUM - super_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:super_admin_access'])->group(function () {
        Route::post('/admin/delete_log', [LogController::class, 'deleteLog']);
        Route::post('/admin/restart_queues', [LogController::class, 'restartQueues']);
        Route::get('/admin/impersonation/schools', [ImpersonationController::class, 'schools']);
        Route::get('/admin/impersonation/users', [ImpersonationController::class, 'users']);
        Route::post('/admin/impersonation/start', [ImpersonationController::class, 'start']);
    });

    /* SANCTUM - super_admin, admin */
    Route::middleware(['auth:sanctum', 'api-allowed:scope:admin_or_super_admin_access'])->group(function () {
        Route::get('/admin/get_log', [LogController::class, 'getLog']);
        Route::get('/admin/list_logs', [LogController::class, 'listLogs']);
    });
});
