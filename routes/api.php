<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\App\AuthController;
// use App\Http\Controllers\Api\V1\App\Athlete\GroupController;
// use App\Http\Controllers\Api\V1\App\Athlete\TrainingController;
use App\Http\Controllers\Client\TrainingsController;
use App\Http\Controllers\Client\TrainingAssignmentsController;
use App\Http\Controllers\Api\V1\App\Client\ProfileController;
use App\Http\Controllers\Api\V1\App\PushTestController;
use App\Http\Controllers\Client\TrainingSectionResultsController;
USE App\Http\Controllers\Api\V1\App\Client\TrainingSessionsController;
use App\Models\UserApp;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí registras las rutas de tu aplicación. Todas se asignan al grupo
| "api" y se cargan por el RouteServiceProvider.
|
*/

Route::prefix('v1')->group(function () {

    // =========================
    // AUTH (APP MÓVIL)
    // =========================
    Route::post('/app/login', [AuthController::class, 'login']);
    Route::post('/app/activate', [AuthController::class, 'activate']);

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

        // =========================
        // ATHLETE
        // =========================
        Route::prefix('app/athlete')->group(function () {
            Route::get('/groups', [GroupController::class, 'index']);
            Route::get('/trainings', [TrainingController::class, 'index']);
            Route::get('/trainings/{training}', [TrainingController::class, 'show']);
        });
    });
});