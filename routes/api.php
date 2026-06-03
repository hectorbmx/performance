<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\App\AuthController;
use App\Http\Controllers\Client\TrainingsController;
use App\Http\Controllers\Client\TrainingAssignmentsController;
use App\Http\Controllers\Api\V1\App\Client\ProfileController;
use App\Http\Controllers\Api\V1\App\Client\MembershipController;
use App\Http\Controllers\Api\V1\App\Client\HealthMetricController;
use App\Http\Controllers\Api\V1\App\Client\StreakController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\App\PushTestController;
use App\Http\Controllers\Client\TrainingSectionResultsController;
USE App\Http\Controllers\Api\V1\App\Client\TrainingSessionsController;
use App\Http\Controllers\Api\V1\App\Client\LibraryVideoController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Http\Controllers\Api\V1\Coach\AuthController as CoachAuthController;
use App\Http\Controllers\Api\V1\Coach\ClientController as CoachApiClientController;
use App\Http\Controllers\Api\V1\Coach\GroupController as CoachApiGroupController;
use App\Http\Controllers\Api\V1\Coach\PlanController as CoachApiPlanController;
use App\Http\Controllers\Api\V1\Coach\SubscriptionController as CoachApiSubscriptionController;
use App\Http\Controllers\Api\V1\Coach\TrainingController as CoachApiTrainingController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí registras las rutas de tu aplicación. Todas se asignan al grupo
| "api" y se cargan por el RouteServiceProvider.
|
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('/billing/coach/checkout', [BillingController::class, 'coachCheckout']);
    Route::post('/billing/client/checkout', [BillingController::class, 'clientCheckout']);
       //para ver videos
        Route::get('library/videos', [LibraryVideoController::class, 'index']);
        Route::get('library/videos/{video}', [LibraryVideoController::class, 'show']);
        Route::get('training/catalog',[LibraryVideoController::class, 'catalog']);

});

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::prefix('v1')->group(function () {

    // =========================
    // AUTH (APP MÓVIL)
    // =========================
    Route::post('/app/login', [AuthController::class, 'login']);
    Route::post('/app/activate', [AuthController::class, 'activate']);

    //para cobrar stripe
    

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/app/logout', [AuthController::class, 'logout']);
        Route::get('/app/me', [AuthController::class, 'me']);
        Route::get('/app/me/profile', [AuthController::class, 'meProfile'])->middleware('auth:sanctum');
        Route::get('/app/ping', fn () => response()->json(['ok' => true]));
        Route::post('resend-activation-code', [AuthController::class, 'resendActivationCode']);
        Route::get('/app/trainings', [TrainingsController::class, 'index']);
        Route::get('/app/training-assignments/{assignment}',[TrainingAssignmentsController::class, 'show']);
        Route::post('/app/training-assignments/{assignment}/start',[TrainingAssignmentsController::class, 'start']);
        Route::post('/app/training-assignments/{assignment}/complete',[TrainingAssignmentsController::class, 'complete']);
        Route::post('/app/training-sections/{section}/results',[TrainingSectionResultsController::class, 'store']);
        Route::put('/app/training-sections/{section}/results', [TrainingSectionResultsController::class, 'update']);

        Route::post('/app/training-assignments/{assignment}/sections/{section}/complete', [TrainingAssignmentsController::class, 'completeSection']);
        
        Route::get('/app/training-sessions/{session}', [TrainingSessionsController::class, 'show']);
        Route::post('/app/training-sessions/{trainingSession}/start', [TrainingSessionsController::class, 'start']);


        Route::patch('/app/me/profile', [AuthController::class, 'updateProfile']);
        Route::patch('/app/me/health-profile', [AuthController::class, 'updateHealthProfile']);
        Route::post('/app/me/body-records', [AuthController::class, 'storeBodyRecord']);
        Route::post('/app/me/metric-records', [AuthController::class, 'storeMetricRecord']);

        Route::post ('/app/register-device', [AuthController::class, 'registerDevice']);

        //test de envio de notificaciones
        Route::post('/app/test/push',[PushTestController::class,'send']);


        //obtiene la foto del atleta
          Route::get('client/profile', [ProfileController::class, 'show']);
        //actualiza la foto desde la app movil
        Route::post('client/profile/avatar', [ProfileController::class, 'storeAvatar']);

        Route::get('/app/memberships', [MembershipController::class, 'index']);
        Route::get('/app/streak', [StreakController::class, 'show']);
        Route::post('/app/memberships/future', [MembershipController::class, 'storeFuture']);
        Route::get('/app/health-metrics', [HealthMetricController::class, 'index']);
        Route::post('/app/health-metrics/sync', [HealthMetricController::class, 'sync']);

     

        Route::patch('/app/athlete/trainings/assignments/{id}/status', [TrainingsController::class, 'updateStatus']);
    });

    Route::prefix('coach')->group(function () {
        Route::post('/login', [CoachAuthController::class, 'login']);

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::post('/logout', [CoachAuthController::class, 'logout']);
            Route::get('/me', [CoachAuthController::class, 'me']);

            Route::middleware('coach.api')->group(function () {
                Route::get('clients/{client}/trainings', [CoachApiClientController::class, 'trainings']);
                Route::apiResource('clients', CoachApiClientController::class);
                Route::post('groups/{group}/clients', [CoachApiGroupController::class, 'attachClient']);
                Route::delete('groups/{group}/clients/{client}', [CoachApiGroupController::class, 'detachClient']);
                Route::apiResource('groups', CoachApiGroupController::class)->except(['destroy']);
                Route::apiResource('plans', CoachApiPlanController::class);
                Route::get('subscriptions', [CoachApiSubscriptionController::class, 'index']);

                Route::get('trainings/meta', [CoachApiTrainingController::class, 'meta']);
                Route::apiResource('trainings', CoachApiTrainingController::class);
            });
        });
    });
});
