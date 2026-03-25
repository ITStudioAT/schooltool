<?php

use App\Http\Controllers\Admin\ABA\AbaAiSettingsController;
use App\Http\Controllers\Admin\ABA\AbaAnalysisRunController;
use App\Http\Controllers\Admin\ABA\AbaAttachmentController;
use App\Http\Controllers\Admin\ABA\AbaChunkUploadController;
use App\Http\Controllers\Admin\ABA\AbaController;
use App\Http\Controllers\Admin\ABA\AbaKnowledgeQueryController;
use App\Http\Controllers\Admin\ABA\AbaSeedHardeningController;
use App\Http\Controllers\Admin\ABA\AbaSeedReportController;
use App\Http\Controllers\Admin\ABA\AbaSeedReviewController;
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
use App\Http\Controllers\Admin\Materials\MaterialTypeController;
use App\Http\Controllers\Admin\Materials\MaterialUserSettingsController;
use App\Http\Controllers\Admin\Materials\MaterialWorkspaceController;
use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\RegisterDateBookingController;
use App\Http\Controllers\Admin\RegisterDateController;
use App\Http\Controllers\Admin\RegisterPrintController;
use App\Http\Controllers\Admin\RegisterUserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SchoolToolController;
use App\Http\Controllers\Admin\SchoolyearController;
use App\Http\Controllers\Admin\SpaRoleController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\TeachersListController;
use App\Http\Controllers\Admin\Teaching\CourseBehaviourEntryController;
use App\Http\Controllers\Admin\Teaching\CourseDateController;
use App\Http\Controllers\Admin\Teaching\CourseStudentCategoryEvaluationController;
use App\Http\Controllers\Admin\Teaching\CourseWorkController;
use App\Http\Controllers\Admin\Teaching\FileUploadController;
use App\Http\Controllers\Admin\Teaching\HolidayController;
use App\Http\Controllers\Admin\Teaching\Import116Controller;
use App\Http\Controllers\Admin\Teaching\MyHolidayController;
use App\Http\Controllers\Admin\Teaching\SchoolHourController;
use App\Http\Controllers\Admin\Teaching\TeachingController;
use App\Http\Controllers\Admin\Teaching\TeachingCourseController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserWithRoleController;
use App\Http\Controllers\Homepage\HomepageController;
use App\Http\Controllers\Homepage\RegisterController;
use App\Http\Controllers\Spa\RouteController;
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
    Route::post('/routes/is_route_allowed', [RouteController::class, 'isRouteAllowed']);
    Route::post('/admin/execute_logout', [AdminController::class, 'executeLogout']);

    /***** HOMEPAGE ROUTES *****/
    Route::get('/homepage/config', [HomepageController::class, 'config']);
    Route::get('/homepage/load_schools_for_tool', [HomepageController::class, 'loadSchoolsForTool']);
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

    /***** ADMIN ROUTES *****/
    Route::get('/admin/config', [AdminController::class, 'config']);

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

        Route::get('/admin/test-queue', [HealthController::class, 'testQueue']);
        Route::get('/admin/test-queue/check', [HealthController::class, 'checkQueueStatus']);
        Route::get('/admin/test-cron/check', [HealthController::class, 'testCron']);
    });

    /* SANCTUM - aba ai-settings + seed-report (admin/super_admin only) */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,super_admin'])->group(function () {
        Route::get('/admin/aba/ai-settings', [AbaAiSettingsController::class, 'index']);
        Route::post('/admin/aba/ai-settings/check-freshness', [AbaAiSettingsController::class, 'checkFreshness']);
        Route::post('/admin/aba/ai-settings/check-freshness-online', [AbaAiSettingsController::class, 'checkFreshnessOnline']);
        Route::post('/admin/aba/ai-settings/propose-update', [AbaAiSettingsController::class, 'proposeSeedUpdate']);
        Route::post('/admin/aba/ai-settings/rebuild', [AbaAiSettingsController::class, 'rebuildFromSeed']);
        Route::post('/admin/aba/ai-settings/pandoc-debug/run', [AbaAiSettingsController::class, 'runPandocDebug']);
        Route::post('/admin/aba/ai-settings/pdf-openai-debug/run', [AbaAiSettingsController::class, 'runPdfOpenAiDebug']);
        Route::get('/admin/aba/seed-report', [AbaSeedReportController::class, 'index']);

        // Seed Hardening Pipeline
        Route::get('/admin/aba/seed-hardening/status', [AbaSeedHardeningController::class, 'status']);
        Route::post('/admin/aba/seed-hardening/scan', [AbaSeedHardeningController::class, 'scan']);
        Route::post('/admin/aba/seed-hardening/run', [AbaSeedHardeningController::class, 'run']);
        Route::post('/admin/aba/seed-hardening/run-ai', [AbaSeedHardeningController::class, 'runAi']);
        Route::post('/admin/aba/seed-hardening/analyze-and-propose', [AbaSeedHardeningController::class, 'analyzeAndPropose']);
        Route::post('/admin/aba/seed-hardening/cleanup', [AbaSeedHardeningController::class, 'cleanup']);

        // Knowledge Query (lokale Wissensbasis abfragen)
        Route::get('/admin/aba/knowledge', [AbaKnowledgeQueryController::class, 'index']);
        Route::post('/admin/aba/knowledge/query', [AbaKnowledgeQueryController::class, 'query']);

        // Seed Review / Diff
        Route::get('/admin/aba/seed-review/proposals', [AbaSeedReviewController::class, 'proposals']);
        Route::get('/admin/aba/seed-review/diff/{filename}', [AbaSeedReviewController::class, 'diff'])->where('filename', '[a-zA-Z0-9_\-\.]+');
        Route::get('/admin/aba/seed-review/content/{filename}', [AbaSeedReviewController::class, 'content'])->where('filename', '[a-zA-Z0-9_\-\.]+');
        Route::put('/admin/aba/seed-review/content/{filename}', [AbaSeedReviewController::class, 'updateContent'])->where('filename', '[a-zA-Z0-9_\-\.]+');
        Route::post('/admin/aba/seed-review/generate-replacement', [AbaSeedReviewController::class, 'generateReplacement']);
        Route::post('/admin/aba/seed-review/apply', [AbaSeedReviewController::class, 'applyReplacement']);
    });

    /* SANCTUM - aba_teacher */
    Route::middleware(['auth:sanctum', 'api-allowed:aba_teacher', 'tool-licensed:ABA,auth'])->group(function () {
        Route::get('/admin/aba/schoolyears', [SchoolyearController::class, 'index']);
        Route::post('/admin/aba/schoolyears/set_active', [SchoolyearController::class, 'setActiveSchoolyear']);

        // ABA CRUD
        Route::get('/admin/abas', [AbaController::class, 'index']);
        Route::post('/admin/abas', [AbaController::class, 'store']);
        Route::get('/admin/abas/{aba}', [AbaController::class, 'show']);
        Route::put('/admin/abas/{aba}', [AbaController::class, 'update']);
        Route::delete('/admin/abas/{aba}', [AbaController::class, 'destroy']);
        Route::post('/admin/abas/{aba}/analysis', [AbaAnalysisRunController::class, 'store']);
        Route::get('/admin/abas/{aba}/analysis/results', [AbaAnalysisRunController::class, 'showLatest']);
        Route::get('/admin/abas/{aba}/analysis/document-review', [AbaAnalysisRunController::class, 'showDocumentReview']);
        Route::get('/admin/abas/{aba}/analysis/document-review/logo-asset', [AbaAnalysisRunController::class, 'showDocumentReviewLogoAsset']);
        Route::post('/admin/abas/{aba}/attachments/from-temp', [AbaAttachmentController::class, 'storeFromTemp']);
        Route::delete('/admin/abas/{aba}/attachments/{attachment}', [AbaAttachmentController::class, 'destroy']);

        // ABA chunk upload for FilePond
        Route::post('/admin/aba/uploads/chunk', [AbaChunkUploadController::class, 'upload']);
        Route::patch('/admin/aba/uploads/chunk', [AbaChunkUploadController::class, 'uploadNext']);
        Route::delete('/admin/aba/uploads/chunk/{upload_id}', [AbaChunkUploadController::class, 'destroy']);
    });

    /* SANCTUM - user */
    Route::middleware(['auth:sanctum', 'api-allowed:user,admin,register_admin,tutoring_admin,teaching_admin,materials_admin,teacher'])->group(function () {
        Route::put('/admin/users/update_profile/{user}', [UserController::class, 'updateProfile']);
        Route::post('/admin/users/update_with_code', [UserController::class, 'updateWithCode']);
        Route::post('/admin/users/save_password', [UserController::class, 'savePassword']);
        Route::post('/admin/users/save_password_with_code', [UserController::class, 'savePasswordWithCode']);

        // RegisterUsers
        Route::get('/admin/register_users', [RegisterUserController::class, 'index'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_users/delete_register_users', [RegisterUserController::class, 'deleteRegisterUsers'])->middleware('tool-licensed:Anmeldetool');
    });

    /* SANCTUM - admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin'])->group(function () {
        Route::get('/admin/users20/load_users', [UserController::class, 'loadUsers']);
        Route::post('/admin/users20/update', [UserController::class, 'updateUser']);
        Route::post('/admin/users20/store', [UserController::class, 'storeUser']);
        Route::post('/admin/users20/delete_users', [UserController::class, 'deleteUsers']);

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
    Route::middleware(['auth:sanctum', 'api-allowed:admin,teaching_admin', 'tool-licensed:Lehrertool'])->group(function () {
        Route::post('/admin/teaching_upload/{slug}', [FileUploadController::class, 'upload']);
        Route::patch('/admin/teaching_upload/{slug}', [FileUploadController::class, 'uploadNext']);
    });

    /* SANCTUM - tutoring_user */
    Route::middleware(['auth:sanctum', 'api-allowed:tutoring_user', 'tool-licensed:Nachhilfetool'])->group(function () {
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
    Route::middleware(['auth:sanctum', 'api-allowed:admin,tutoring_admin,register_admin,teacher'])->group(function () {
        Route::get('/admin/school_tools/load_config', [SchoolToolController::class, 'loadConfig']);
        Route::post('/admin/users20/toggle_is_active', [UserController::class, 'toggleIsActive']);
    });

    /* SANCTUM - admin, materials_admin, materials_moderator */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,materials_admin,materials_moderator'])->group(function () {
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
    Route::middleware(['auth:sanctum', 'api-allowed:admin,tutoring_admin'])->group(function () {});

    /* SANCTUM - admin, tutoring_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,tutoring_admin', 'tool-licensed:Nachhilfetool'])->group(function () {
        Route::post('/admin/school_tools/save_tutoring_settings', [SchoolToolController::class, 'saveTutoringSettings']);
        Route::apiResource('/admin/tutoring/subjects', App\Http\Controllers\Admin\Tutoring\SubjectController::class)->names('admin.tutoring.subjects');
        Route::apiResource('/admin/tutoring/users', App\Http\Controllers\Admin\Tutoring\UserController::class)->names('admin.tutoring.users');
        Route::post('/admin/tutoring/create_subjects', [App\Http\Controllers\Admin\Tutoring\SubjectController::class, 'createSubjects']);
        Route::post('/admin/tutoring/delete_users', [App\Http\Controllers\Admin\Tutoring\UserController::class, 'deleteUsers']);
        Route::post('/admin/tutoring/clean_users', [App\Http\Controllers\Admin\Tutoring\UserController::class, 'cleanUsers']);
        Route::post('/admin/tutoring/confirm_users', [App\Http\Controllers\Admin\Tutoring\UserController::class, 'confirmUsers']);
    });

    /* SANCTUM - admin, teaching_admin, teacher */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,teaching_admin,teacher', 'tool-licensed:Lehrertool'])->group(function () {
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
        Route::apiResource('/admin/teaching/courses', TeachingCourseController::class);
        Route::apiResource('/admin/teaching/course_dates', CourseDateController::class);
        Route::patch('/admin/teaching/course_dates/{course_date}/status', [CourseDateController::class, 'updateStatus']);
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

    /* SANCTUM - admin, materials_admin, materials_moderator */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,materials_admin,materials_moderator', 'tool-licensed:Materialientool'])->group(function () {
        Route::get('/admin/materials/config', [MaterialController::class, 'config']);
        Route::post('/admin/materials/workspaces', [MaterialWorkspaceController::class, 'store']);
        Route::put('/admin/materials/workspaces/{material_workspace}', [MaterialWorkspaceController::class, 'update']);
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
        Route::get('/admin/materials/cards/last-deleted-restore-info', [MaterialController::class, 'lastDeletedRestoreInfo']);
        Route::post('/admin/materials/cards/restore-last-deleted', [MaterialController::class, 'restoreLastDeleted']);
        Route::post('/admin/materials/cards/restore-deleted/{card_id}', [MaterialController::class, 'restoreDeletedById'])->whereNumber('card_id');
        Route::delete('/admin/materials/cards/deleted/{card_id}', [MaterialController::class, 'purgeDeletedById'])->whereNumber('card_id');
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
        Route::post('/admin/materials/shares/inbox/subjects/{material_subject}/insert-tree', [MaterialShareController::class, 'insertInboxSubjectTree']);
        Route::put('/admin/materials/shares/inbox/subjects/{material_subject}', [MaterialShareController::class, 'updateInboxSubject']);
        Route::put('/admin/materials/shares/inbox/topics/{material_topic}', [MaterialShareController::class, 'updateInboxTopic']);
        Route::put('/admin/materials/shares/inbox/units/{material_unit}', [MaterialShareController::class, 'updateInboxUnit']);
        Route::post('/admin/materials/shares/inbox/subjects/{material_subject}/materials', [MaterialShareController::class, 'storeInboxSubjectMaterial']);
        Route::post('/admin/materials/shares/inbox/topics/{material_topic}/materials', [MaterialShareController::class, 'storeInboxTopicMaterial']);
        Route::post('/admin/materials/shares/inbox/units/{material_unit}/materials', [MaterialShareController::class, 'storeInboxUnitMaterial']);
        Route::delete('/admin/materials/shares/inbox/subjects/{material_subject}', [MaterialShareController::class, 'destroyInboxSubject']);
        Route::delete('/admin/materials/shares/inbox/topics/{material_topic}', [MaterialShareController::class, 'destroyInboxTopic']);
        Route::delete('/admin/materials/shares/inbox/units/{material_unit}', [MaterialShareController::class, 'destroyInboxUnit']);
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
    Route::middleware(['auth:sanctum', 'api-allowed:admin,materials_admin,materials_moderator', 'tool-licensed:Materialientool'])->group(function () {
        Route::get('/admin/materials/types', [MaterialTypeController::class, 'index']);
        Route::post('/admin/materials/types', [MaterialTypeController::class, 'store']);
        Route::put('/admin/materials/types/{material_type}', [MaterialTypeController::class, 'update']);
        Route::delete('/admin/materials/types/{material_type}', [MaterialTypeController::class, 'destroy']);
    });

    /* SANCTUM - admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin', 'tool-licensed:Materialientool'])->group(function () {
        Route::get('/admin/materials/statuses', [MaterialStatusController::class, 'index']);
        Route::post('/admin/materials/statuses', [MaterialStatusController::class, 'store']);
        Route::put('/admin/materials/statuses/{material_status}', [MaterialStatusController::class, 'update']);
        Route::delete('/admin/materials/statuses/{material_status}', [MaterialStatusController::class, 'destroy']);
        Route::put('/admin/materials/file-settings', [MaterialFileSettingsController::class, 'update']);
    });

    /* SANCTUM - admin, register_admin, tutoring_admin, teaching_admin, materials_admin, materials_moderator, teacher */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,register_admin,tutoring_admin,teaching_admin,materials_admin,materials_moderator,teacher'])->group(function () {

        // Tutoring, Offers
        Route::apiResource('/admin/tutoring/offers', App\Http\Controllers\Admin\Tutoring\OfferController::class)->names('admin.tutoring.offers')->middleware('tool-licensed:Nachhilfetool');
        Route::post('/admin/tutoring/delete_offers', [App\Http\Controllers\Admin\Tutoring\OfferController::class, 'deleteOffers'])->middleware('tool-licensed:Nachhilfetool');
        Route::post('/admin/tutoring/toggle_active_offer', [App\Http\Controllers\Admin\Tutoring\OfferController::class, 'toggleActiveOffer'])->middleware('tool-licensed:Nachhilfetool');
        Route::post('/admin/tutoring/toggle_accepted_offer', [App\Http\Controllers\Admin\Tutoring\OfferController::class, 'toggleAcceptedOffer'])->middleware('tool-licensed:Nachhilfetool');
        Route::get('/admin/tutoring/get_stats', [App\Http\Controllers\Admin\Tutoring\OfferController::class, 'getStats'])->middleware('tool-licensed:Nachhilfetool');
        // /admin/tutoring/get_stats

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
        Route::post('/admin/schools/load_school_infos', [SchoolController::class, 'loadSchoolInfos']);
        Route::post('/admin/schools/add_licence', [SchoolController::class, 'addLicence']);
        Route::post('/admin/schools/delete_licence', [SchoolController::class, 'deleteLicence']);
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

        // Profile
        Route::post('/admin/users/save_2fa', [UserController::class, 'save2Fa']);
        Route::post('/admin/users/save_2fa_with_code', [UserController::class, 'save2FaWithCode']);

        // schoolyears
        Route::apiResource('/admin/schoolyears', SchoolyearController::class);
        Route::post('/admin/schoolyears/set_active', [SchoolyearController::class, 'setActiveSchoolyear']);
        Route::get('/admin/schoolyears_paginate', [SchoolyearController::class, 'indexPaginate']);

        // registers
        Route::apiResource('/admin/registers', App\Http\Controllers\Admin\RegisterController::class)->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/set_active', [App\Http\Controllers\Admin\RegisterController::class, 'setActiveRegister'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/get_active', [App\Http\Controllers\Admin\RegisterController::class, 'getActiveRegisters'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/toggle', [App\Http\Controllers\Admin\RegisterController::class, 'toggleRegister'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/print_excel', [RegisterPrintController::class, 'printExcel'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/print_supervisor', [RegisterPrintController::class, 'printSupervisor'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/print_date', [RegisterPrintController::class, 'printDate'])->middleware('tool-licensed:Anmeldetool');

        // register_dates
        Route::apiResource('/admin/register_dates', RegisterDateController::class)->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/create_dates', [RegisterDateController::class, 'createDates'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/load_days', [RegisterDateController::class, 'loadDays'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/filter_register_dates', [RegisterDateController::class, 'filterRegisterDates'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/lock_register_dates', [RegisterDateController::class, 'lockRegisterDates'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/unlock_register_dates', [RegisterDateController::class, 'unlockRegisterDates'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/delete_register_dates', [RegisterDateController::class, 'deleteRegisterDates'])->middleware('tool-licensed:Anmeldetool');

        // register_date_bookings
        Route::apiResource('/admin/register_date_bookings', RegisterDateBookingController::class)->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_date_bookings/get_user_with_email', [RegisterDateBookingController::class, 'getUserWithEmail'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_date_bookings/update_or_create_user', [RegisterDateBookingController::class, 'updateOrCreateUser'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_date_bookings/delete_bookings', [RegisterDateBookingController::class, 'deleteBookings'])->middleware('tool-licensed:Anmeldetool');
    });

    /* SANCTUM - admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin'])->group(function () {

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
    Route::middleware(['auth:sanctum', 'api-allowed:super_admin'])->group(function () {
        Route::post('/admin/delete_log', [LogController::class, 'deleteLog']);
        Route::post('/admin/restart_queues', [LogController::class, 'restartQueues']);
        Route::get('/admin/impersonation/schools', [ImpersonationController::class, 'schools']);
        Route::get('/admin/impersonation/users', [ImpersonationController::class, 'users']);
        Route::post('/admin/impersonation/start', [ImpersonationController::class, 'start']);
    });

    /* SANCTUM - super_admin, admin */
    Route::middleware(['auth:sanctum', 'api-allowed:super_admin,admin'])->group(function () {
        Route::get('/admin/get_log', [LogController::class, 'getLog']);
        Route::get('/admin/list_logs', [LogController::class, 'listLogs']);
    });
});
