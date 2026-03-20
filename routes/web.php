<?php

use App\Http\Controllers\Homepage\HomepageController;
use App\Http\Controllers\Tutoring\OfferController;
use App\Http\Controllers\Tutoring\OfferRequestController;
use App\Http\Controllers\Tutoring\TutoringController;
use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\GetProcessorRequest;
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
        'web-allowed:admin,register_admin,tutoring_admin,teacher,lunch_admin',
        'tool-licensed:Anmeldetool,auth',
    ]);

    Route::get('/admin/tutoring/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:admin,register_admin,tutoring_admin,teacher,lunch_admin',
        'tool-licensed:Nachhilfetool,auth',
    ]);

    Route::get('/admin/teaching/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:admin,register_admin,tutoring_admin,teacher,lunch_admin',
        'tool-licensed:Lehrertool,auth',
    ]);

    Route::get('/admin/materials/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:admin,materials_admin,materials_moderator',
        'tool-licensed:Materialientool,auth',
    ]);

    Route::get('/admin/aba/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware(['auth:sanctum', 'aba-access']);

    Route::get('/admin/{any?}', function () {
        return view('spa::admin');
    })->where('any', '.*')->middleware([
        'auth:sanctum',
        'web-allowed:admin,register_admin,tutoring_admin,teaching_admin,materials_admin,materials_moderator,teacher,lunch_admin,aba_teacher',
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

    if (config('schooltool.tutoring_active') === true) {

        Route::prefix('homepage')->group(function () {
            Route::get('tutoring_response', fn () => view('homepage'));
            Route::get('tutoring_overview', fn () => view('homepage'))->middleware('tool-licensed:Nachhilfetool');
            Route::get('tutoring', fn () => view('homepage'))->middleware(['auth:sanctum', 'tool-licensed:Nachhilfetool']);
        });

        Route::prefix('homepage/tutoring')->group(function () {
            Route::get('confirm-user', [TutoringController::class, 'confirmUser']);
            Route::get('refuse-user', [TutoringController::class, 'refuseUser']);
            Route::get('offer', [OfferController::class, 'offerConfirmRefuse']);
            Route::get('offer_request', [OfferRequestController::class, 'offerRequest']);
        });
    }

    Route::get('/', function () {
        return view('homepage');
    });

    Route::get('/student/{any?}', function () {
        return view('homepage');
    })->where('any', '.*');

    Route::get('/homepage/{any?}', [HomepageController::class, 'routing']);

    /*
    |--------------------------------------------------------------------------
    | GOOGLE DOCUMENT AI TEST ROUTE
    |--------------------------------------------------------------------------
    */
    Route::get('/test-google-document-ai', function () {
        try {
            $projectId = env('GOOGLE_CLOUD_PROJECT_ID');
            $location = env('GOOGLE_DOCUMENT_AI_LOCATION', 'eu');
            $processorId = env('GOOGLE_DOCUMENT_AI_PROCESSOR_ID');
            $credentialsRelativePath = env('GOOGLE_DOCUMENT_AI_CREDENTIALS');

            if (! $projectId || ! $location || ! $processorId || ! $credentialsRelativePath) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Missing required env values.',
                    'env' => [
                        'GOOGLE_CLOUD_PROJECT_ID' => $projectId,
                        'GOOGLE_DOCUMENT_AI_LOCATION' => $location,
                        'GOOGLE_DOCUMENT_AI_PROCESSOR_ID' => $processorId,
                        'GOOGLE_DOCUMENT_AI_CREDENTIALS' => $credentialsRelativePath,
                    ],
                ], 500);
            }

            $credentialsPath = base_path($credentialsRelativePath);

            if (! file_exists($credentialsPath)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Credentials file not found.',
                    'credentials_path' => $credentialsPath,
                ], 500);
            }

            putenv('GOOGLE_APPLICATION_CREDENTIALS='.$credentialsPath);

            $client = new DocumentProcessorServiceClient([
                'apiEndpoint' => $location.'-documentai.googleapis.com',
            ]);

            $processorName = $client->processorName(
                $projectId,
                $location,
                $processorId
            );

            $request = (new GetProcessorRequest)
                ->setName($processorName);

            $processor = $client->getProcessor($request);

            return response()->json([
                'ok' => true,
                'project_id' => $projectId,
                'location' => $location,
                'processor_id' => $processorId,
                'api_endpoint' => $location.'-documentai.googleapis.com',
                'processor_name' => $processor->getName(),
                'processor_type' => $processor->getType(),
                'display_name' => $processor->getDisplayName(),
                'state' => $processor->getState(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ], 500);
        }
    });
});
