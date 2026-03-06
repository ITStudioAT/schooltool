<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\SpaRoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserWithRoleController;
use App\Http\Controllers\Homepage\HomepageController;
use App\Http\Controllers\Spa\RouteController;
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
    Route::post('/homepage/logout', [\App\Http\Controllers\Homepage\HomepageController::class, 'logout']);

    /***** STUDENT ROUTES *****/
    Route::get('/homepage/student/config', [\App\Http\Controllers\Student\StudentController::class, 'config']);
    Route::post('/homepage/student/login_step_email', [\App\Http\Controllers\Student\StudentController::class, 'loginStepEmail'])->middleware('tool-licensed:Lehrertool');
    Route::post('/homepage/student/login_step_code', [\App\Http\Controllers\Student\StudentController::class, 'loginStepCode'])->middleware('tool-licensed:Lehrertool');
    Route::post('/homepage/student/login_step_password', [\App\Http\Controllers\Student\StudentController::class, 'loginStepPassword'])->middleware('tool-licensed:Lehrertool');
    Route::get('/homepage/student/user', [\App\Http\Controllers\Student\StudentController::class, 'user'])->middleware('tool-licensed:Lehrertool');
    Route::post('/homepage/student/change_password', [\App\Http\Controllers\Student\StudentController::class, 'changePassword'])->middleware('tool-licensed:Lehrertool');
    Route::get('/homepage/student/courses', [\App\Http\Controllers\Student\CourseController::class, 'index'])->middleware('tool-licensed:Lehrertool');
    Route::get('/homepage/student/courses/{courseId}', [\App\Http\Controllers\Student\CourseController::class, 'show'])->middleware('tool-licensed:Lehrertool');
    Route::get('/homepage/student/courses/{courseId}/entries', [\App\Http\Controllers\Student\CourseStudentEntryController::class, 'index'])->middleware('tool-licensed:Lehrertool');

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
    Route::get('/homepage/register/config', [\App\Http\Controllers\Homepage\RegisterController::class, 'config'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/check_email', [\App\Http\Controllers\Homepage\RegisterController::class, 'checkEmail'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/confirm_email', [\App\Http\Controllers\Homepage\RegisterController::class, 'confirmEmail'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/save_user_data', [\App\Http\Controllers\Homepage\RegisterController::class, 'saveUserData'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/login_token', [\App\Http\Controllers\Homepage\RegisterController::class, 'loginToken'])->middleware('tool-licensed:Anmeldetool');
    Route::get('/homepage/register/load_register_and_user', [\App\Http\Controllers\Homepage\RegisterController::class, 'loadRegisterAndUser'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/book', [\App\Http\Controllers\Homepage\RegisterController::class, 'book'])->middleware('tool-licensed:Anmeldetool');
    Route::post('/homepage/register/delete_booking', [\App\Http\Controllers\Homepage\RegisterController::class, 'deleteBooking'])->middleware('tool-licensed:Anmeldetool');

    /* homepage/tutoring */
    Route::get('/homepage/tutoring/config', [\App\Http\Controllers\Tutoring\TutoringController::class, 'config']);
    Route::post('/homepage/tutoring/check_email', [\App\Http\Controllers\Tutoring\TutoringController::class, 'checkEMail'])->middleware('tool-licensed:Nachhilfetool');
    Route::post('/homepage/tutoring/confirm_email', [\App\Http\Controllers\Tutoring\TutoringController::class, 'confirmEMail'])->middleware('tool-licensed:Nachhilfetool');
    Route::post('/homepage/tutoring/create_user', [\App\Http\Controllers\Tutoring\TutoringController::class, 'createUser'])->middleware('tool-licensed:Nachhilfetool');
    Route::post('/homepage/tutoring/unknown_password', [\App\Http\Controllers\Tutoring\TutoringController::class, 'unknownPassword'])->middleware('tool-licensed:Nachhilfetool');
    Route::post('/homepage/tutoring/login_with_token', [\App\Http\Controllers\Tutoring\TutoringController::class, 'loginWithToken'])->middleware('tool-licensed:Nachhilfetool');
    Route::post('/homepage/tutoring/login_with_password', [\App\Http\Controllers\Tutoring\TutoringController::class, 'loginWithPassword'])->middleware('tool-licensed:Nachhilfetool');
    Route::get('/homepage/tutoring/load_offer_config', [\App\Http\Controllers\Tutoring\OfferController::class, 'loadOfferConfig']);
    Route::get('/homepage/tutoring/load_offers', [\App\Http\Controllers\Tutoring\OfferController::class, 'loadOffers']);
    Route::post('/homepage/tutoring/click_count', [\App\Http\Controllers\Tutoring\OfferController::class, 'clickCount']);
    Route::post('/homepage/tutoring/set_user_search_criteria', [\App\Http\Controllers\Tutoring\OfferController::class, 'setUserSearchCriteria']);
    // setUserSearchCriteria

    // Public: returns a guest-safe "not impersonating" response when unauthenticated.
    Route::get('/admin/impersonation/status', [\App\Http\Controllers\Admin\ImpersonationController::class, 'status']);

    /* SANCTUM */
    Route::middleware(['auth:sanctum'])->group(function () {
        // navigation, menus
        Route::get('/admin/navigation/profile_menu', [NavigationController::class, 'profileMenu']);
        Route::get('/admin/navigation/user_menu', [NavigationController::class, 'userMenu']);

        // users
        Route::apiResource('/admin/users', UserController::class)->names('admin.users');
        Route::post('/admin/impersonation/stop', [\App\Http\Controllers\Admin\ImpersonationController::class, 'stop']);

        Route::get('/admin/test-queue', [App\Http\Controllers\Admin\HealthController::class, 'testQueue']);
        Route::get('/admin/test-queue/check', [App\Http\Controllers\Admin\HealthController::class, 'checkQueueStatus']);
        Route::get('/admin/test-cron/check', [App\Http\Controllers\Admin\HealthController::class, 'testCron']);
    });

    /* SANCTUM - user */
    Route::middleware(['auth:sanctum', 'api-allowed:user,admin,register_admin,tutoring_admin,teaching_admin,materials_admin,teacher'])->group(function () {
        Route::put('/admin/users/update_profile/{user}', [UserController::class, 'updateProfile']);
        Route::post('/admin/users/update_with_code', [UserController::class, 'updateWithCode']);
        Route::post('/admin/users/save_password', [UserController::class, 'savePassword']);
        Route::post('/admin/users/save_password_with_code', [UserController::class, 'savePasswordWithCode']);

        // RegisterUsers
        Route::get('/admin/register_users', [\App\Http\Controllers\Admin\RegisterUserController::class, 'index'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_users/delete_register_users', [\App\Http\Controllers\Admin\RegisterUserController::class, 'deleteRegisterUsers'])->middleware('tool-licensed:Anmeldetool');
    });

    /* SANCTUM - admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin'])->group(function () {
        Route::get('/admin/users20/load_users', [\App\Http\Controllers\Admin\UserController::class, 'loadUsers']);
        Route::post('/admin/users20/update', [\App\Http\Controllers\Admin\UserController::class, 'updateUser']);
        Route::post('/admin/users20/store', [\App\Http\Controllers\Admin\UserController::class, 'storeUser']);
        Route::post('/admin/users20/delete_users', [\App\Http\Controllers\Admin\UserController::class, 'deleteUsers']);

        // teachers, teachers_list
        Route::apiResource('/admin/teachers', \App\Http\Controllers\Admin\TeacherController::class);
        Route::post('/admin/teachers/delete_teachers', [\App\Http\Controllers\Admin\TeacherController::class, 'deleteTeachers']);
        Route::apiResource('/admin/teachers_list', \App\Http\Controllers\Admin\TeachersListController::class);
        Route::post('/admin/teachers_list/delete_teachers', [\App\Http\Controllers\Admin\TeachersListController::class, 'deleteTeachers']);

        Route::post('/admin/teachers_list_upload', [\App\Http\Controllers\Admin\TeachersListController::class, 'upload']);
        Route::patch('/admin/teachers_list_upload', [\App\Http\Controllers\Admin\TeachersListController::class, 'uploadNext']);

        // SchoolTool - Active Schoolyear
        Route::post('/admin/school_tools/set_active_schoolyear', [\App\Http\Controllers\Admin\SchoolToolController::class, 'setActiveSchoolyear']);
    });

    /* SANCTUM - admin, teaching_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,teaching_admin', 'tool-licensed:Lehrertool'])->group(function () {
        Route::post('/admin/teaching_upload/{slug}', [\App\Http\Controllers\Admin\Teaching\FileUploadController::class, 'upload']);
        Route::patch('/admin/teaching_upload/{slug}', [\App\Http\Controllers\Admin\Teaching\FileUploadController::class, 'uploadNext']);
    });

    /* SANCTUM - tutoring_user */
    Route::middleware(['auth:sanctum', 'api-allowed:tutoring_user', 'tool-licensed:Nachhilfetool'])->group(function () {
        Route::apiResource('/homepage/tutoring/users', \App\Http\Controllers\Tutoring\UserController::class)->names('tutoring.users');
        Route::post('/homepage/tutoring/update_password', [\App\Http\Controllers\Tutoring\UserController::class, 'updatePassword']);
        Route::post('/homepage/tutoring/logout', [\App\Http\Controllers\Tutoring\UserController::class, 'logout']);
        Route::get('/homepage/tutoring/load_auth', [\App\Http\Controllers\Tutoring\TutoringController::class, 'loadAuth']);
        Route::get('/homepage/tutoring/load_my_offers', [\App\Http\Controllers\Tutoring\OfferController::class, 'loadMyOffers']);
        Route::apiResource('/homepage/tutoring/subjects', \App\Http\Controllers\Tutoring\SubjectController::class)->names('tutoring.subjects');
        Route::apiResource('/homepage/tutoring/offers', \App\Http\Controllers\Tutoring\OfferController::class)->names('tutoring.offers');
        Route::post('/homepage/tutoring/toggle_offer', [\App\Http\Controllers\Tutoring\OfferController::class, 'toggleOffer']);
        Route::post('/homepage/tutoring/send_request', [\App\Http\Controllers\Tutoring\OfferController::class, 'sendRequest']);
        Route::apiResource('/homepage/tutoring/offer_requests', \App\Http\Controllers\Tutoring\OfferRequestController::class)->names('tutoring.offer_requests');
        Route::get('/homepage/tutoring/received_offer_requests', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'receivedRequests']);
        Route::post('/homepage/tutoring/request_mail_clicked', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'requestMailClicked']);
        Route::post('/homepage/tutoring/to_archive', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'toArchive']);
        Route::post('/homepage/tutoring/to_active', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'toActive']);
        Route::post('/homepage/tutoring/to_user_archive', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'toUserArchive']);
        Route::post('/homepage/tutoring/to_user_active', [\App\Http\Controllers\Tutoring\OfferRequestController::class, 'toUserActive']);

        // api/homepage/tutoring/offer_requests
    });

    /* SANCTUM - admin, tutoring_admin, register_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,tutoring_admin,register_admin,teacher'])->group(function () {
        Route::get('/admin/school_tools/load_config', [\App\Http\Controllers\Admin\SchoolToolController::class, 'loadConfig']);
        Route::post('/admin/users20/toggle_is_active', [\App\Http\Controllers\Admin\UserController::class, 'toggleIsActive']);
    });

    /* SANCTUM - admin, materials_admin, materials_moderator */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,materials_admin,materials_moderator'])->group(function () {
        Route::apiResource('/admin/groups', \App\Http\Controllers\Admin\GroupController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/admin/groups/{group}/members', [\App\Http\Controllers\Admin\GroupController::class, 'members']);
        Route::get('/admin/groups/{group}/source-members', [\App\Http\Controllers\Admin\GroupController::class, 'sourceMembers']);
        Route::delete('/admin/groups/{group}/members/{user}', [\App\Http\Controllers\Admin\GroupController::class, 'removeMember']);
        Route::post('/admin/groups/{group}/remove-users', [\App\Http\Controllers\Admin\GroupController::class, 'removeMembers']);
        Route::get('/admin/groups/{group}/assignable-users', [\App\Http\Controllers\Admin\GroupController::class, 'assignableUsers']);
        Route::post('/admin/groups/{group}/assign-users', [\App\Http\Controllers\Admin\GroupController::class, 'assignUsers']);
        Route::get('/admin/groups/{group}/assignable-groups', [\App\Http\Controllers\Admin\GroupController::class, 'assignableGroups']);
        Route::post('/admin/groups/{group}/assign-from-group', [\App\Http\Controllers\Admin\GroupController::class, 'assignFromGroup']);
        Route::get('/admin/groups/{group}/my-teaching-courses', [\App\Http\Controllers\Admin\GroupController::class, 'myTeachingCourses']);
    });

    /* SANCTUM - admin, tutoring_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,tutoring_admin'])->group(function () {});

    /* SANCTUM - admin, tutoring_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,tutoring_admin', 'tool-licensed:Nachhilfetool'])->group(function () {
        Route::post('/admin/school_tools/save_tutoring_settings', [\App\Http\Controllers\Admin\SchoolToolController::class, 'saveTutoringSettings']);
        Route::apiResource('/admin/tutoring/subjects', \App\Http\Controllers\Admin\Tutoring\SubjectController::class)->names('admin.tutoring.subjects');
        Route::apiResource('/admin/tutoring/users', \App\Http\Controllers\Admin\Tutoring\UserController::class)->names('admin.tutoring.users');
        Route::post('/admin/tutoring/create_subjects', [\App\Http\Controllers\Admin\Tutoring\SubjectController::class, 'createSubjects']);
        Route::post('/admin/tutoring/delete_users', [\App\Http\Controllers\Admin\Tutoring\UserController::class, 'deleteUsers']);
        Route::post('/admin/tutoring/clean_users', [\App\Http\Controllers\Admin\Tutoring\UserController::class, 'cleanUsers']);
        Route::post('/admin/tutoring/confirm_users', [\App\Http\Controllers\Admin\Tutoring\UserController::class, 'confirmUsers']);
    });

    /* SANCTUM - admin, teaching_admin, teacher */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,teaching_admin,teacher', 'tool-licensed:Lehrertool'])->group(function () {
        Route::get('/admin/teaching/search116', [\App\Http\Controllers\Admin\Teaching\TeachingController::class, 'search116']);
        Route::get('/admin/teaching/load_settings', [\App\Http\Controllers\Admin\Teaching\TeachingController::class, 'loadSettings']);
        Route::post('/admin/teaching/save_settings', [\App\Http\Controllers\Admin\Teaching\TeachingController::class, 'saveSettings']);
        Route::post('/admin/teaching/save_active_semester', [\App\Http\Controllers\Admin\Teaching\TeachingController::class, 'saveActiveSemester']);
        Route::post('/admin/teaching/save_semester_2_date', [\App\Http\Controllers\Admin\Teaching\TeachingController::class, 'saveSemester2Date']);
        Route::apiResource('/admin/teaching/courses', \App\Http\Controllers\Admin\Teaching\TeachingCourseController::class);
        Route::apiResource('/admin/teaching/course_dates', \App\Http\Controllers\Admin\Teaching\CourseDateController::class);
        Route::patch('/admin/teaching/course_dates/{course_date}/status', [\App\Http\Controllers\Admin\Teaching\CourseDateController::class, 'updateStatus']);
        Route::apiResource('/admin/teaching/holidays', \App\Http\Controllers\Admin\Teaching\HolidayController::class)->only(['index', 'store', 'destroy']);
        Route::apiResource('/admin/teaching/school_hours', \App\Http\Controllers\Admin\Teaching\SchoolHourController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('/admin/teaching/my_holidays', \App\Http\Controllers\Admin\Teaching\MyHolidayController::class)
            ->only(['index', 'store', 'destroy'])
            ->parameters(['my_holidays' => 'my_holiday']);
        Route::get('/admin/teaching/load_class_students', [\App\Http\Controllers\Admin\Teaching\StudentController::class, 'loadClassStudents']);
        Route::get('/admin/teaching/import116/load_class_students', [\App\Http\Controllers\Admin\Teaching\Import116Controller::class, 'loadClassStudents']);
        Route::get('/admin/teaching/import116/runs', [\App\Http\Controllers\Admin\Teaching\Import116Controller::class, 'runs']);
        Route::get('/admin/teaching/import116/runs/{import116_run}', [\App\Http\Controllers\Admin\Teaching\Import116Controller::class, 'runDetails']);
        Route::post('/admin/teaching/import116/runs/reset', [\App\Http\Controllers\Admin\Teaching\Import116Controller::class, 'resetRuns']);
        Route::delete('/admin/teaching/import116/runs/{import116_run}', [\App\Http\Controllers\Admin\Teaching\Import116Controller::class, 'destroyRun']);
        Route::apiResource('/admin/teaching/course_works', \App\Http\Controllers\Admin\Teaching\CourseWorkController::class);
        Route::apiResource('/admin/teaching/course_student_entries', \App\Http\Controllers\Admin\Teaching\CourseStudentEntryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('/admin/teaching/course_behaviour_entries', \App\Http\Controllers\Admin\Teaching\CourseBehaviourEntryController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    /* SANCTUM - admin, materials_admin, materials_moderator */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,materials_admin,materials_moderator', 'tool-licensed:Materialientool'])->group(function () {
        Route::get('/admin/materials/config', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'config']);
        Route::post('/admin/materials/subjects', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'storeSubject']);
        Route::put('/admin/materials/subjects/{material_subject}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'updateSubject']);
        Route::delete('/admin/materials/subjects/{material_subject}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'destroySubject']);
        Route::post('/admin/materials/subjects/{material_subject}/move', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'moveSubject']);
        Route::post('/admin/materials/subjects/{material_subject}/convert-to-topic', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'convertSubjectToTopic']);
        Route::post('/admin/materials/topics', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'storeTopic']);
        Route::put('/admin/materials/topics/{material_topic}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'updateTopic']);
        Route::delete('/admin/materials/topics/{material_topic}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'destroyTopic']);
        Route::post('/admin/materials/topics/{material_topic}/move', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'moveTopic']);
        Route::post('/admin/materials/topics/{material_topic}/unlink', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'unlinkTopic']);
        Route::post('/admin/materials/topics/{material_topic}/move-to-subject', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'moveTopicToSubject']);
        Route::post('/admin/materials/topics/{material_topic}/convert-to-subject', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'convertTopicToSubject']);
        Route::post('/admin/materials/topics/{material_topic}/convert-to-unit', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'convertTopicToUnit']);
        Route::post('/admin/materials/units', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'storeUnit']);
        Route::put('/admin/materials/units/{material_unit}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'updateUnit']);
        Route::delete('/admin/materials/units/{material_unit}', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'destroyUnit']);
        Route::post('/admin/materials/units/{material_unit}/move', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'moveUnit']);
        Route::post('/admin/materials/units/{material_unit}/move-to-topic', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'moveUnitToTopic']);
        Route::post('/admin/materials/units/{material_unit}/convert-to-topic', [\App\Http\Controllers\Admin\Materials\MaterialClassificationController::class, 'convertUnitToTopic']);
        Route::post('/admin/materials/units/{material_unit}/unlink', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'unlinkUnit']);
        Route::put('/admin/materials/user-settings', [\App\Http\Controllers\Admin\Materials\MaterialUserSettingsController::class, 'update']);
        Route::get('/admin/materials/cards', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'index']);
        Route::post('/admin/materials/cards', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'store']);
        Route::post('/admin/materials/cards/quick_store', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'quickStore']);
        Route::get('/admin/materials/cards/deleted-restore-list', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'deletedRestoreList']);
        Route::get('/admin/materials/cards/last-deleted-restore-info', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'lastDeletedRestoreInfo']);
        Route::post('/admin/materials/cards/restore-last-deleted', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'restoreLastDeleted']);
        Route::post('/admin/materials/cards/restore-deleted/{card_id}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'restoreDeletedById'])->whereNumber('card_id');
        Route::delete('/admin/materials/cards/deleted/{card_id}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'purgeDeletedById'])->whereNumber('card_id');
        Route::get('/admin/materials/cards/{material_card}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'show']);
        Route::put('/admin/materials/cards/{material_card}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'update']);
        Route::post('/admin/materials/cards/{material_card}/unlink', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'unlink']);
        Route::delete('/admin/materials/cards/{material_card}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'destroy']);
        Route::post('/admin/materials/cards/{material_card}/attachments/link', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'storeLinkAttachment']);
        Route::post('/admin/materials/cards/{material_card}/attachments/image-url', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'storeRemoteImageAttachment']);
        Route::post('/admin/materials/cards/{material_card}/attachments/file', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'storeFileAttachment']);
        Route::post('/admin/materials/cards/{material_card}/attachments/file-temp', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'storeTempFileAttachment']);
        Route::patch('/admin/materials/attachments/{material_card_attachment}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'updateAttachment']);
        Route::get('/admin/materials/attachments/{material_card_attachment}/text-content', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'textAttachmentContent']);
        Route::patch('/admin/materials/attachments/{material_card_attachment}/text-content', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'updateTextAttachmentContent']);
        Route::delete('/admin/materials/attachments/{material_card_attachment}', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'destroyAttachment']);
        Route::get('/admin/materials/attachments/{material_card_attachment}/preview', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'previewAttachment']);
        Route::get('/admin/materials/attachments/{material_card_attachment}/download', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'downloadAttachment']);
        Route::get('/admin/materials/attachments/{material_card_attachment}/download-docx', [\App\Http\Controllers\Admin\Materials\MaterialController::class, 'downloadAttachmentDocx']);
        Route::get('/admin/materials/shares', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'index']);
        Route::patch('/admin/materials/shares/{material_share_rule}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'updateRule']);
        Route::post('/admin/materials/shares/targets', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'storeTarget']);
        Route::patch('/admin/materials/shares/targets/{material_share_target}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'updateTarget']);
        Route::delete('/admin/materials/shares/targets/{material_share_target}', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'destroyTarget']);
        Route::get('/admin/materials/shares/lookup-users', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'lookupUsers']);
        Route::get('/admin/materials/shares/lookup-groups', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'lookupGroups']);
        Route::get('/admin/materials/shares/lookup-schools', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'lookupSchools']);
        Route::get('/admin/materials/shares/lookup-external-user', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'lookupExternalUser']);
        Route::get('/admin/materials/shares/inbox-users', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'inboxUsers']);
        Route::get('/admin/materials/shares/inbox/material-attachments', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'inboxMaterialAttachments']);
        Route::get('/admin/materials/shares/inbox/material-detail', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'inboxMaterialDetail']);
        Route::post('/admin/materials/shares/inbox/archive', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'archiveInboxRule']);
        Route::post('/admin/materials/shares/inbox/unarchive', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'unarchiveInboxRule']);
        Route::post('/admin/materials/shares/inbox/material-original-copy', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'copyInboxMaterialAsOriginal']);
        Route::post('/admin/materials/shares/inbox/material-insert', [\App\Http\Controllers\Admin\Materials\MaterialShareController::class, 'insertInboxMaterial']);
        Route::post('/admin/materials/uploads/chunk', [\App\Http\Controllers\Admin\Materials\MaterialChunkUploadController::class, 'upload']);
        Route::patch('/admin/materials/uploads/chunk', [\App\Http\Controllers\Admin\Materials\MaterialChunkUploadController::class, 'uploadNext']);
        Route::delete('/admin/materials/uploads/chunk/{upload_id}', [\App\Http\Controllers\Admin\Materials\MaterialChunkUploadController::class, 'destroy']);
    });

    /* SANCTUM - admin, materials_admin, materials_moderator */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,materials_admin,materials_moderator', 'tool-licensed:Materialientool'])->group(function () {
        Route::get('/admin/materials/types', [\App\Http\Controllers\Admin\Materials\MaterialTypeController::class, 'index']);
        Route::post('/admin/materials/types', [\App\Http\Controllers\Admin\Materials\MaterialTypeController::class, 'store']);
        Route::put('/admin/materials/types/{material_type}', [\App\Http\Controllers\Admin\Materials\MaterialTypeController::class, 'update']);
        Route::delete('/admin/materials/types/{material_type}', [\App\Http\Controllers\Admin\Materials\MaterialTypeController::class, 'destroy']);
    });

    /* SANCTUM - admin */
    Route::middleware(['auth:sanctum', 'api-allowed:admin', 'tool-licensed:Materialientool'])->group(function () {
        Route::get('/admin/materials/statuses', [\App\Http\Controllers\Admin\Materials\MaterialStatusController::class, 'index']);
        Route::post('/admin/materials/statuses', [\App\Http\Controllers\Admin\Materials\MaterialStatusController::class, 'store']);
        Route::put('/admin/materials/statuses/{material_status}', [\App\Http\Controllers\Admin\Materials\MaterialStatusController::class, 'update']);
        Route::delete('/admin/materials/statuses/{material_status}', [\App\Http\Controllers\Admin\Materials\MaterialStatusController::class, 'destroy']);
        Route::put('/admin/materials/file-settings', [\App\Http\Controllers\Admin\Materials\MaterialFileSettingsController::class, 'update']);
    });

    /* SANCTUM - admin, register_admin, tutoring_admin, teaching_admin, materials_admin, materials_moderator, teacher */
    Route::middleware(['auth:sanctum', 'api-allowed:admin,register_admin,tutoring_admin,teaching_admin,materials_admin,materials_moderator,teacher'])->group(function () {

        // Tutoring, Offers
        Route::apiResource('/admin/tutoring/offers', \App\Http\Controllers\Admin\Tutoring\OfferController::class)->names('admin.tutoring.offers')->middleware('tool-licensed:Nachhilfetool');
        Route::post('/admin/tutoring/delete_offers', [\App\Http\Controllers\Admin\Tutoring\OfferController::class, 'deleteOffers'])->middleware('tool-licensed:Nachhilfetool');
        Route::post('/admin/tutoring/toggle_active_offer', [\App\Http\Controllers\Admin\Tutoring\OfferController::class, 'toggleActiveOffer'])->middleware('tool-licensed:Nachhilfetool');
        Route::post('/admin/tutoring/toggle_accepted_offer', [\App\Http\Controllers\Admin\Tutoring\OfferController::class, 'toggleAcceptedOffer'])->middleware('tool-licensed:Nachhilfetool');
        Route::get('/admin/tutoring/get_stats', [\App\Http\Controllers\Admin\Tutoring\OfferController::class, 'getStats'])->middleware('tool-licensed:Nachhilfetool');
        // /admin/tutoring/get_stats

        // Roles
        Route::get('/admin/roles/load_roles', [\App\Http\Controllers\Admin\RoleController::class, 'loadRoles']);

        // licences
        Route::apiResource('/admin/licences', \App\Http\Controllers\Admin\LicenceController::class);
        Route::post('/admin/licences/load_licences', [\App\Http\Controllers\Admin\LicenceController::class, 'loadLicences']);
        Route::post('/admin/licences/delete_licences', [\App\Http\Controllers\Admin\LicenceController::class, 'deleteLicences']);
        Route::put('/admin/licences/{licence}/save_licence_model', [\App\Http\Controllers\Admin\LicenceController::class, 'saveLicenceModel']);

        // schools
        Route::apiResource('/admin/schools', \App\Http\Controllers\Admin\SchoolController::class);
        Route::post('/admin/schools_upload/uploadLogo', [\App\Http\Controllers\Admin\SchoolController::class, 'uploadLogo']);
        Route::patch('/admin/schools_upload/uploadLogo', [\App\Http\Controllers\Admin\SchoolController::class, 'uploadLogoNext']);
        Route::post('/admin/schools/delete_schools', [\App\Http\Controllers\Admin\SchoolController::class, 'deleteSchools']);
        Route::post('/admin/schools/load_switchable_schools', [\App\Http\Controllers\Admin\SchoolController::class, 'loadSwitchableSchools']);
        Route::post('/admin/schools/search_switch_users', [\App\Http\Controllers\Admin\SchoolController::class, 'searchSwitchUsers']);
        Route::post('/admin/schools/switch_school', [\App\Http\Controllers\Admin\SchoolController::class, 'switchSchool']);
        Route::post('/admin/schools/load_school_infos', [\App\Http\Controllers\Admin\SchoolController::class, 'loadSchoolInfos']);
        Route::post('/admin/schools/add_licence', [\App\Http\Controllers\Admin\SchoolController::class, 'addLicence']);
        Route::post('/admin/schools/delete_licence', [\App\Http\Controllers\Admin\SchoolController::class, 'deleteLicence']);
        Route::put('/admin/school_licences/{school_licence}/save_licence_model', [\App\Http\Controllers\Admin\SchoolController::class, 'saveSchoolLicenceModel']);
        Route::get('/admin/school_licences/{school_licence}/users', [\App\Http\Controllers\Admin\SchoolController::class, 'loadSchoolLicenceUsers']);
        Route::get('/admin/school_licences/{school_licence}/users/{user}/roles', [\App\Http\Controllers\Admin\SchoolController::class, 'loadSchoolLicenceUserRoles']);
        Route::put('/admin/school_licences/{school_licence}/users/{user}/roles', [\App\Http\Controllers\Admin\SchoolController::class, 'saveSchoolLicenceUserRoles']);
        Route::put('/admin/school_licences/{school_licence}/users/{user}/spatie_roles', [\App\Http\Controllers\Admin\SchoolController::class, 'saveSchoolLicenceUserSpatieRoles']);
        Route::post('/admin/school_licences/{school_licence}/activate_user_licence', [\App\Http\Controllers\Admin\SchoolController::class, 'activateCurrentUserLicence']);
        Route::post('/admin/school_licences/{school_licence}/renew_user_licence', [\App\Http\Controllers\Admin\SchoolController::class, 'renewCurrentUserLicence']);
        Route::post('/admin/school_licences/{school_licence}/deactivate_user_licence', [\App\Http\Controllers\Admin\SchoolController::class, 'deactivateCurrentUserLicence']);
        Route::post('/admin/schools/add_admin', [\App\Http\Controllers\Admin\SchoolController::class, 'addAdmin']);
        Route::post('/admin/schools/delete_admin', [\App\Http\Controllers\Admin\SchoolController::class, 'deleteAdmin']);

        // Profile
        Route::post('/admin/users/save_2fa', [UserController::class, 'save2Fa']);
        Route::post('/admin/users/save_2fa_with_code', [UserController::class, 'save2FaWithCode']);

        // schoolyears
        Route::apiResource('/admin/schoolyears', \App\Http\Controllers\Admin\SchoolyearController::class);
        Route::post('/admin/schoolyears/set_active', [\App\Http\Controllers\Admin\SchoolyearController::class, 'setActiveSchoolyear']);
        Route::get('/admin/schoolyears_paginate', [\App\Http\Controllers\Admin\SchoolyearController::class, 'indexPaginate']);

        // registers
        Route::apiResource('/admin/registers', \App\Http\Controllers\Admin\RegisterController::class)->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/set_active', [\App\Http\Controllers\Admin\RegisterController::class, 'setActiveRegister'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/get_active', [\App\Http\Controllers\Admin\RegisterController::class, 'getActiveRegisters'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/toggle', [\App\Http\Controllers\Admin\RegisterController::class, 'toggleRegister'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/print_excel', [\App\Http\Controllers\Admin\RegisterPrintController::class, 'printExcel'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/print_supervisor', [\App\Http\Controllers\Admin\RegisterPrintController::class, 'printSupervisor'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/registers/print_date', [\App\Http\Controllers\Admin\RegisterPrintController::class, 'printDate'])->middleware('tool-licensed:Anmeldetool');

        // register_dates
        Route::apiResource('/admin/register_dates', \App\Http\Controllers\Admin\RegisterDateController::class)->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/create_dates', [\App\Http\Controllers\Admin\RegisterDateController::class, 'createDates'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/load_days', [\App\Http\Controllers\Admin\RegisterDateController::class, 'loadDays'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/filter_register_dates', [\App\Http\Controllers\Admin\RegisterDateController::class, 'filterRegisterDates'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/lock_register_dates', [\App\Http\Controllers\Admin\RegisterDateController::class, 'lockRegisterDates'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/unlock_register_dates', [\App\Http\Controllers\Admin\RegisterDateController::class, 'unlockRegisterDates'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_dates/delete_register_dates', [\App\Http\Controllers\Admin\RegisterDateController::class, 'deleteRegisterDates'])->middleware('tool-licensed:Anmeldetool');

        // register_date_bookings
        Route::apiResource('/admin/register_date_bookings', \App\Http\Controllers\Admin\RegisterDateBookingController::class)->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_date_bookings/get_user_with_email', [\App\Http\Controllers\Admin\RegisterDateBookingController::class, 'getUserWithEmail'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_date_bookings/update_or_create_user', [\App\Http\Controllers\Admin\RegisterDateBookingController::class, 'updateOrCreateUser'])->middleware('tool-licensed:Anmeldetool');
        Route::post('/admin/register_date_bookings/delete_bookings', [\App\Http\Controllers\Admin\RegisterDateBookingController::class, 'deleteBookings'])->middleware('tool-licensed:Anmeldetool');
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
        Route::post('/admin/load_roles', [\App\Http\Controllers\Admin\AdminController::class, 'loadRoles']);

        // users_with_roles
        Route::get('/admin/users_with_roles/roles', [UserWithRoleController::class, 'roles']);
        Route::post('/admin/users_with_roles/roles', [UserWithRoleController::class, 'saveUserRoles']);
        Route::apiResource('/admin/users_with_roles', UserWithRoleController::class);
    });

    /* SANCTUM - super_admin */
    Route::middleware(['auth:sanctum', 'api-allowed:super_admin'])->group(function () {
        Route::post('/admin/delete_log', [\App\Http\Controllers\Admin\LogController::class, 'deleteLog']);
        Route::post('/admin/restart_queues', [\App\Http\Controllers\Admin\LogController::class, 'restartQueues']);
        Route::get('/admin/impersonation/schools', [\App\Http\Controllers\Admin\ImpersonationController::class, 'schools']);
        Route::get('/admin/impersonation/users', [\App\Http\Controllers\Admin\ImpersonationController::class, 'users']);
        Route::post('/admin/impersonation/start', [\App\Http\Controllers\Admin\ImpersonationController::class, 'start']);
    });

    /* SANCTUM - super_admin, admin */
    Route::middleware(['auth:sanctum', 'api-allowed:super_admin,admin'])->group(function () {
        Route::get('/admin/get_log', [\App\Http\Controllers\Admin\LogController::class, 'getLog']);
        Route::get('/admin/list_logs', [\App\Http\Controllers\Admin\LogController::class, 'listLogs']);
    });
});
