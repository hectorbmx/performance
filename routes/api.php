<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\App\AuthController;
use App\Http\Controllers\Client\TrainingsController;
use App\Http\Controllers\Client\TrainingAssignmentsController;
use App\Http\Controllers\Api\V1\App\Client\ProfileController;
use App\Http\Controllers\Api\V1\App\Client\MembershipController;
use App\Http\Controllers\Api\V1\App\Client\HealthMetricController;
use App\Http\Controllers\Api\V1\App\Client\StreakController;
use App\Http\Controllers\Api\V1\App\Client\LiftingPerformanceController;
use App\Http\Controllers\Api\V1\App\Client\TipController as AppTipController;
use App\Http\Controllers\Api\V1\App\Client\ActivityCalendarController;
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
use App\Http\Controllers\Api\V1\Coach\LibraryVideoController as CoachApiLibraryVideoController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| AquÃ­ registras las rutas de tu aplicaciÃ³n. Todas se asignan al grupo
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
    // AUTH (APP MÃ“VIL)
    // =========================
    Route::post('/app/login', [\App\Http\Controllers\Api\V1\App\AthleteAccountController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/app/register', [\App\Http\Controllers\Api\V1\App\AthleteAccountController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/app/email/verify', [\App\Http\Controllers\Api\V1\App\AthleteAccountController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('/app/email/resend', [\App\Http\Controllers\Api\V1\App\AthleteAccountController::class, 'requestCode'])->defaults('purpose','verify')->middleware('throttle:5,1');
    Route::post('/app/password/forgot', [\App\Http\Controllers\Api\V1\App\AthleteAccountController::class, 'requestCode'])->defaults('purpose','reset')->middleware('throttle:5,1');
    Route::post('/app/password/reset', [\App\Http\Controllers\Api\V1\App\AthleteAccountController::class, 'resetPassword'])->middleware('throttle:10,1');
    Route::post('/app/activate', [AuthController::class, 'activate']);

    //para cobrar stripe
    

    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureAthleteAccountActive::class])->group(function () {
        Route::post('/app/logout', [AuthController::class, 'logout']);
        Route::get('/app/me', [\App\Http\Controllers\Api\V1\App\AthleteAccountController::class, 'me']);
        Route::get('/app/account/profile', [\App\Http\Controllers\Api\V1\App\AthleteAccountController::class, 'profile']);
        Route::patch('/app/account/profile', [\App\Http\Controllers\Api\V1\App\AthleteAccountController::class, 'profile']);
        Route::delete('/app/account', [\App\Http\Controllers\Api\V1\App\AthleteAccountController::class, 'deleteAccount'])->middleware('throttle:5,1');
        Route::get('/app/ping', fn () => response()->json(['ok' => true]));
        Route::post('resend-activation-code', [AuthController::class, 'resendActivationCode']);

        Route::post ('/app/register-device', [AuthController::class, 'registerDevice']);

        //test de envio de notificaciones
        Route::post('/app/test/push',[PushTestController::class,'send']);

        Route::get('/app/memberships', [MembershipController::class, 'index']);
        Route::post('/app/memberships/future', [MembershipController::class, 'storeFuture']);
        Route::post('/app/memberships/renewal-checkout', [MembershipController::class, 'storeRenewal']);
        Route::get('/app/tips/categories', [AppTipController::class, 'categories'])->name('app.tips.categories');
        Route::get('/app/tips', [AppTipController::class, 'index'])->name('app.tips.index');
        Route::get('/app/tips/{tip}', [AppTipController::class, 'show'])->whereNumber('tip')->name('app.tips.show');
        Route::get('/app/tips/{tip}/image', [AppTipController::class, 'image'])->whereNumber('tip')->name('app.tips.image');

        // Existing execution history remains readable after commercial expiry.
        // These handlers still require an active athlete and current ownership.
        Route::get('/app/workout-ownership', [\App\Http\Controllers\Api\V1\WorkoutOwnershipController::class, 'show']);
        Route::get('/app/workout-executions', [\App\Http\Controllers\Api\V1\App\Client\WorkoutExecutionController::class, 'index']);
        Route::post('/app/workout-executions', [\App\Http\Controllers\Api\V1\App\Client\WorkoutExecutionController::class, 'store']);
        Route::get('/app/workout-executions/{execution}', [\App\Http\Controllers\Api\V1\App\Client\WorkoutExecutionController::class, 'show'])->whereUuid('execution');
        Route::post('/app/workout-executions/{execution}/complete', [\App\Http\Controllers\Api\V1\App\Client\WorkoutExecutionController::class, 'complete'])->whereUuid('execution');
        Route::put('/app/workout-executions/{execution}/sets/{set}', [\App\Http\Controllers\Api\V1\App\Client\WorkoutExecutionController::class, 'saveSet'])->whereUuid('execution')->whereUuid('set');
        Route::get('/app/workout-executions/{execution}/sets/{set}/revisions', [\App\Http\Controllers\Api\V1\App\Client\WorkoutExecutionController::class, 'setRevisions'])->whereUuid('execution')->whereUuid('set');

        Route::get('/app/exercise-catalog/meta', [\App\Http\Controllers\Api\V1\WorkoutExerciseCatalogController::class, 'meta']);
        Route::get('/app/exercise-catalog', [\App\Http\Controllers\Api\V1\WorkoutExerciseCatalogController::class, 'index']);
        Route::get('/app/exercise-catalog/{exercise}', [\App\Http\Controllers\Api\V1\WorkoutExerciseCatalogController::class, 'show'])->whereNumber('exercise');

        Route::get('/app/workout-progress', [\App\Http\Controllers\Api\V1\WorkoutProgressController::class, 'athleteProgress']);
        Route::get('/app/workout-progress/exercises', [\App\Http\Controllers\Api\V1\WorkoutProgressController::class, 'athleteExercises']);
        Route::get('/app/exercise-maxes', [\App\Http\Controllers\Api\V1\WorkoutMaxController::class, 'athleteIndex']);
        Route::post('/app/workout-executions/{execution}/replace-exercise', [\App\Http\Controllers\Api\V1\WorkoutGeneratorController::class, 'replaceExercise'])->whereUuid('execution');
        Route::post('/app/workout-generator/executions', [\App\Http\Controllers\Api\V1\WorkoutGeneratorController::class, 'createExecution']);
        Route::get('/app/workout-generator/settings', [\App\Http\Controllers\Api\V1\WorkoutGeneratorController::class, 'athleteSettings']);
        Route::patch('/app/workout-generator/settings', [\App\Http\Controllers\Api\V1\WorkoutGeneratorController::class, 'updateAthleteSettings']);
        Route::post('/app/workout-goals', [\App\Http\Controllers\Api\V1\WorkoutProgressController::class, 'athleteGoal']);
        Route::post('/app/exercise-maxes', [\App\Http\Controllers\Api\V1\WorkoutMaxController::class, 'athleteStore']);
        Route::post('/app/exercise-maxes/estimate', [\App\Http\Controllers\Api\V1\WorkoutMaxController::class, 'athleteEstimate']);
        Route::post('/app/workout-generator/prescription', [\App\Http\Controllers\Api\V1\WorkoutGeneratorController::class, 'prescribe']);
        Route::post('/app/workout-generator/recommendations', [\App\Http\Controllers\Api\V1\WorkoutGeneratorController::class, 'recommend']);

        Route::middleware('client.membership')->group(function () {
            Route::get('/app/me/profile', [AuthController::class, 'meProfile']);
            Route::patch('/app/me/profile', [AuthController::class, 'updateProfile']);
            Route::patch('/app/me/health-profile', [AuthController::class, 'updateHealthProfile']);
            Route::post('/app/me/body-records', [AuthController::class, 'storeBodyRecord']);
            Route::post('/app/me/metric-records', [AuthController::class, 'storeMetricRecord']);

            Route::get('/app/trainings', [TrainingsController::class, 'index']);
            Route::get('/app/training-assignments/{assignment}',[TrainingAssignmentsController::class, 'show']);
            Route::post('/app/training-assignments/{assignment}/start',[TrainingAssignmentsController::class, 'start']);
            Route::post('/app/training-assignments/{assignment}/complete',[TrainingAssignmentsController::class, 'complete']);
            Route::post('/app/training-assignments/{assignment}/lifting-sets',[TrainingAssignmentsController::class, 'saveLiftingSet']);
            Route::post('/app/training-assignments/{assignment}/sections/{section}/complete', [TrainingAssignmentsController::class, 'completeSection']);

            Route::post('/app/training-sections/{section}/results',[TrainingSectionResultsController::class, 'store']);
            Route::put('/app/training-sections/{section}/results', [TrainingSectionResultsController::class, 'update']);

            Route::get('/app/training-sessions/{session}', [TrainingSessionsController::class, 'show']);
            Route::get('/app/training-sessions/{trainingSession}/assignment', [TrainingSessionsController::class, 'resolveAssignment']);
            Route::post('/app/training-sessions/{trainingSession}/start', [TrainingSessionsController::class, 'start']);

            //obtiene la foto del atleta
            Route::get('client/profile', [ProfileController::class, 'show']);
            //actualiza la foto desde la app movil
            Route::post('client/profile/avatar', [ProfileController::class, 'storeAvatar']);

            Route::get('/app/streak', [StreakController::class, 'show']);
            Route::get('/app/activity-calendar', [ActivityCalendarController::class, 'index']);
            Route::get('/app/lifting-performance', [LiftingPerformanceController::class, 'show']);
            Route::get('/app/health-metrics', [HealthMetricController::class, 'index']);
            Route::post('/app/health-metrics/sync', [HealthMetricController::class, 'sync']);

            Route::patch('/app/athlete/trainings/assignments/{id}/status', [TrainingsController::class, 'updateStatus']);
        });
    });

    Route::prefix('coach')->group(function () {
        Route::post('/login', [CoachAuthController::class, 'login']);

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::post('/logout', [CoachAuthController::class, 'logout']);
            Route::get('/me', [CoachAuthController::class, 'me']);

            Route::middleware('coach.api')->group(function () {
                Route::get('clients/{client}/trainings', [CoachApiClientController::class, 'trainings']);
                Route::get('clients/{client}/trainings/{assignment}/records', [CoachApiClientController::class, 'trainingRecords']);
                Route::post('clients/{client}/memberships', [CoachApiSubscriptionController::class, 'storeForClient']);
                Route::post('clients/{client}/memberships/{membership}/payments', [CoachApiSubscriptionController::class, 'storeManualPayment']);
                Route::apiResource('clients', CoachApiClientController::class);
                Route::post('groups/{group}/clients', [CoachApiGroupController::class, 'attachClient']);
                Route::delete('groups/{group}/clients/{client}', [CoachApiGroupController::class, 'detachClient']);
                Route::apiResource('groups', CoachApiGroupController::class)->except(['destroy']);
                Route::apiResource('plans', CoachApiPlanController::class);
                Route::get('subscriptions', [CoachApiSubscriptionController::class, 'index']);
                Route::get('library/meta', [CoachApiLibraryVideoController::class, 'meta']);
                Route::get('library', [CoachApiLibraryVideoController::class, 'index']);
                Route::post('library', [CoachApiLibraryVideoController::class, 'store']);
                Route::delete('library/{video}', [CoachApiLibraryVideoController::class, 'destroy']);

                Route::get('clients/{client}/workout-progress', [\App\Http\Controllers\Api\V1\WorkoutProgressController::class, 'coachProgress']);
                Route::post('clients/{client}/workout-goals', [\App\Http\Controllers\Api\V1\WorkoutProgressController::class, 'coachGoal']);
                Route::get('clients/{client}/workout-executions', [\App\Http\Controllers\Api\V1\WorkoutProgressController::class, 'sessions']);
                Route::get('clients/{client}/workout-executions/{execution}', [\App\Http\Controllers\Api\V1\WorkoutProgressController::class, 'session'])->whereUuid('execution');
                Route::get('clients/{client}/exercise-maxes', [\App\Http\Controllers\Api\V1\WorkoutMaxController::class, 'coachIndex']);
                Route::post('clients/{client}/exercise-maxes', [\App\Http\Controllers\Api\V1\WorkoutMaxController::class, 'coachStore']);
                Route::post('clients/{client}/exercise-maxes/estimate', [\App\Http\Controllers\Api\V1\WorkoutMaxController::class, 'coachEstimate']);
                Route::post('clients/{client}/workout-generator/prescription-preview', [\App\Http\Controllers\Api\V1\WorkoutGeneratorController::class, 'prescriptionPreview']);
                Route::get('clients/{client}/workout-generator/settings', [\App\Http\Controllers\Api\V1\WorkoutGeneratorController::class, 'coachSettings']);
                Route::put('clients/{client}/workout-generator/settings', [\App\Http\Controllers\Api\V1\WorkoutGeneratorController::class, 'updateSettings']);
                Route::post('clients/{client}/workout-generator/preview', [\App\Http\Controllers\Api\V1\WorkoutGeneratorController::class, 'preview']);

                Route::get('exercise-catalog/meta', [\App\Http\Controllers\Api\V1\WorkoutExerciseCatalogController::class, 'meta']);
                Route::get('exercise-catalog', [\App\Http\Controllers\Api\V1\WorkoutExerciseCatalogController::class, 'index']);
                Route::get('exercise-catalog/{exercise}', [\App\Http\Controllers\Api\V1\WorkoutExerciseCatalogController::class, 'show'])->whereNumber('exercise');
                Route::post('exercise-catalog', [\App\Http\Controllers\Api\V1\WorkoutExerciseCatalogController::class, 'store']);
                Route::put('exercise-catalog/{exercise}', [\App\Http\Controllers\Api\V1\WorkoutExerciseCatalogController::class, 'update'])->whereNumber('exercise');

                Route::get('trainings/meta', [CoachApiTrainingController::class, 'meta']);
                Route::apiResource('trainings', CoachApiTrainingController::class);
            });
        });
    });
});
