<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CoachController;
use App\Http\Controllers\Admin\MembershipPlanController;
use App\Http\Controllers\Admin\CoachSubscriptionController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Coach\DashboardController as CoachDashboardController;
use App\Http\Controllers\Coach\ClientController as CoachClientController;
use App\Http\Controllers\Coach\CoachClientPlanController;
use App\Http\Controllers\Coach\CoachClientMembershipController;
use App\Http\Controllers\Coach\ClientPaymentController;
use App\Http\Controllers\Coach\TrainingController;
use App\Models\CoachSubscription;
use App\Http\Controllers\Coach\TrainingSessionController;
use App\Http\Controllers\Coach\GroupClientController;
use App\Http\Controllers\Coach\GroupController;
use App\Http\Controllers\Coach\GroupTrainingAssignmentController;
use App\Http\Controllers\Coach\ConfigController;
use App\Http\Controllers\Coach\LibraryController;
use App\Http\Controllers\Coach\SectionLibraryVideoController;

use App\Http\Controllers\Coach\Config\TrainingTypeCatalogController;
use App\Http\Controllers\Coach\Config\TrainingGoalCatalogController;
use App\Http\Controllers\Coach\Config\SectionTypeCatalogController;
use App\Http\Controllers\Coach\Config\MetricCatalogController;

use App\Http\Controllers\Coach\Config\TrainingSectionMetricController;
use App\Http\Controllers\Coach\CoachClientTrainingController;
use App\Http\Controllers\Coach\MetricsController;
use App\Http\Controllers\Coach\ClientHealthProfileController;
use App\Http\Controllers\Coach\ClientMetricRecordController;
use App\Http\Controllers\LegalController;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('/', function () {
    if (auth()->check() && auth()->user()->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }
    if (auth()->check() && auth()->user()->hasRole('coach')) {
        return redirect()->route('coach.dashboard');
    }

    return redirect()->route('login');

    
});
Route::get('/privacy-policy', [LegalController::class, 'privacy'])
    ->name('legal.privacy');
Route::get('/support', [LegalController::class, 'support'])
    ->name('legal.support');
Route::get('/marketing', [LegalController::class, 'marketing'])
    ->name('legal.marketing');

Route::get('/copyright', [LegalController::class, 'copyright'])
    ->name('legal.copyright');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('coaches/{coach}/toggle-status', [CoachController::class, 'toggleStatus'])->name('coaches.toggleStatus');
        Route::resource('coaches', CoachController::class);
        Route::resource('plans', MembershipPlanController::class)->except(['show']);

        Route::post('plans/{plan}/toggle-active',[MembershipPlanController::class, 'toggleActive'])->name('plans.toggleActive');

        Route::get('subscriptions/create', [CoachSubscriptionController::class, 'create'])->name('subscriptions.create');
        Route::post('subscriptions', [CoachSubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::get('subscriptions', [CoachSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::resource('payments', PaymentController::class);
        Route::post('/plans', [MembershipPlanController::class, 'store'])->name('plans.store');   
    });
//rutas coach para setear sus metricas preferidas
 
//rutas coach

Route::prefix('coach')->name('coach.')->middleware(['auth'])->group(function () {

    // Route::prefix('config')->name('config.')->group(function () {
    //     Route::resource('types', TrainingTypeCatalogController::class)->except(['show']);
    //     });
   Route::get('groups/search', [GroupController::class, 'search'])->name('groups.search'); // ✅ NUEVO: buscador de grupos

    Route::resource('groups', GroupClientController::class);
    Route::resource('groups', GroupController::class);
    
    Route::get('clients/search', [CoachClientController::class, 'search'])->name('clients.search');

    // routes/web.php
    Route::put('clients/{client}/health-profile', [ClientHealthProfileController::class, 'update'])->name('clients.health-profile.update');

    Route::post('clients/{client}/metric-records', [ClientMetricRecordController::class, 'store'])->name('clients.metric-records.store');

    Route::delete('clients/{client}/metric-records/{record}', [ClientMetricRecordController::class, 'destroy'])->name('clients.metric-records.destroy');


    // Para asignar clientes al grupo
    Route::post('groups/{group}/clients', [GroupClientController::class, 'store'])->name('groups.clients.store');
    Route::delete('groups/{group}/clients/{client}', [GroupClientController::class, 'destroy'])->name('groups.clients.destroy');

    // Para asignar entrenamientos (con fecha)
    Route::post('groups/{group}/trainings', [GroupTrainingAssignmentController::class, 'store'])->name('groups.trainings.store');
    Route::delete('groups/{group}/trainings/{assignment}', [GroupTrainingAssignmentController::class, 'destroy'])->name('groups.trainings.destroy');

    // Route::post('clients/{client}/resend-activation-code', [ClientController::class, 'resendActivationCode'])
            // ->name('coach.resendActivationCode');
    Route::post('clients/{client}/resend-activation-code', [CoachClientController::class, 'resendActivationCode'])->name('clients.resendActivationCode');
    Route::get('clients/{client}/trainings', [CoachClientTrainingController::class, 'index'])->name('clients.trainings.index');
    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');

    Route::post('/library', [LibraryController::class, 'store'])->name('library.store');

    Route::delete('/library/{video}', [LibraryController::class, 'destroy'])->name('library.destroy');

    Route::post('/training-sections/{section}/library-videos', [SectionLibraryVideoController::class, 'store'])
        ->name('sections.library-videos.store');

    Route::delete('/training-sections/{section}/library-videos/{video}', [SectionLibraryVideoController::class, 'destroy'])
        ->name('sections.library-videos.destroy');

    Route::prefix('config')->name('config.')->group(function () {
        Route::get('/',                [ConfigController::class, 'index'])->name('index');
        Route::get('types',            [TrainingTypeCatalogController::class, 'index'])->name('types.index');
        Route::get('types/create',     [TrainingTypeCatalogController::class, 'create'])->name('types.create');
        Route::post('types',           [TrainingTypeCatalogController::class, 'store'])->name('types.store');
        Route::get('types/{type}/edit',[TrainingTypeCatalogController::class, 'edit'])->name('types.edit');
        Route::put('types/{type}',     [TrainingTypeCatalogController::class, 'update'])->name('types.update');
        Route::delete('types/{type}',  [TrainingTypeCatalogController::class, 'destroy'])->name('types.destroy');

        Route::get('goals',            [TrainingGoalCatalogController::class, 'index'])->name('goals.index');
        Route::get('goals/create',     [TrainingGoalCatalogController::class, 'create'])->name('goals.create');
        Route::post('goals',           [TrainingGoalCatalogController::class, 'store'])->name('goals.store');
        Route::get('goals/{goal}/edit',[TrainingGoalCatalogController::class, 'edit'])->name('goals.edit');
        Route::put('goals/{goal}',     [TrainingGoalCatalogController::class, 'update'])->name('goals.update');
        Route::delete('goals/{goal}',  [TrainingGoalCatalogController::class, 'destroy'])->name('goals.destroy');

        Route::get('section-types',                   [SectionTypeCatalogController::class, 'index'])->name('section-types.index');
        Route::get('section-types/create',            [SectionTypeCatalogController::class, 'create'])->name('section-types.create');
        Route::post('section-types',                  [SectionTypeCatalogController::class, 'store'])->name('section-types.store');
        Route::get('section-types/{sectionType}/edit',[SectionTypeCatalogController::class, 'edit'])->name('section-types.edit');
        Route::put('section-types/{sectionType}',     [SectionTypeCatalogController::class, 'update'])->name('section-types.update');
        Route::delete('section-types/{sectionType}',  [SectionTypeCatalogController::class, 'destroy'])->name('section-types.destroy');

        Route::get('metrics',              [MetricCatalogController::class, 'index'])->name('metrics.index');
        Route::get('metrics/create',       [MetricCatalogController::class, 'create'])->name('metrics.create');
        Route::post('metrics',             [MetricCatalogController::class, 'store'])->name('metrics.store');
        Route::get('metrics/{metric}/edit',[MetricCatalogController::class, 'edit'])->name('metrics.edit');
        Route::put('metrics/{metric}',     [MetricCatalogController::class, 'update'])->name('metrics.update');
        Route::delete('metrics/{metric}',  [MetricCatalogController::class, 'destroy'])->name('metrics.destroy');

        // Listar métricas de una sección
        Route::get('sections/{section}/metrics', [TrainingSectionMetricController::class, 'index'])->name('sections.metrics.index');
        
        Route::get('sections/{section}/metrics/create', [TrainingSectionMetricController::class, 'create'])->name('sections.metrics.create');
        Route::post('sections/{section}/metrics',    [TrainingSectionMetricController::class, 'store'])->name('sections.metrics.store');
        Route::get('sections/{section}/metrics/{sectionMetric}/edit',   [TrainingSectionMetricController::class, 'edit'])->name('sections.metrics.edit');
        Route::put('sections/{section}/metrics/{sectionMetric}',  [TrainingSectionMetricController::class, 'update'])->name('sections.metrics.update');
        Route::delete('sections/{section}/metrics/{sectionMetric}',  [TrainingSectionMetricController::class, 'destroy'])->name('sections.metrics.destroy');

        //VISTA CALENDARIO POR ATLETA

      Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('coach-metrics', [MetricsController::class, 'index'])->name('coach-metrics.index');
        Route::put('coach-metrics', [MetricsController::class, 'update'])->name('coach-metrics.update');
        Route::post('coach-metrics/catalog', [MetricsController::class, 'storeCatalog'])->name('coach-metrics.catalog.store');
        Route::put('coach-metrics/catalog/{metric}', [MetricsController::class, 'updateCatalog'])->name('coach-metrics.catalog.update');
        Route::delete('coach-metrics/catalog/{metric}', [MetricsController::class, 'destroyCatalog'])->name('coach-metrics.catalog.destroy');
     
        });


    });

});

Route::prefix('coach')->name('coach.')->group(function () {

    // Coach logueado pero bloqueado por cobro (NO lleva coach.subscription)
    Route::get('/blocked', function () {
        $sub = CoachSubscription::where('coach_id', auth()->id())
            ->orderByDesc('ends_at')
            ->first();

        return view('coach.billing.blocked', [
            'reason' => 'Tu suscripción está pendiente de pago y la gracia ya venció.',
            'subscription' => $sub,
        ]);
    })->middleware(['auth', 'role:coach'])->name('blocked');

    // Panel coach protegido por suscripción
    Route::get('/', [CoachDashboardController::class, 'index'])
        ->middleware(['auth', 'role:coach', 'coach.subscription'])
        ->name('dashboard');

    // Rutas de Clientes
    Route::resource('clients', CoachClientController::class)
        ->middleware(['auth', 'role:coach', 'coach.subscription'])
        ->except(['show']);

    // Rutas de Membresías (Planes para clientes)
    Route::resource('membresias', CoachClientPlanController::class)
        ->middleware(['auth', 'role:coach', 'coach.subscription'])
        ->except(['show']);

            // Rutas para asignar membresías a clientes
    Route::get('clients/{client}/assign-membership', [CoachClientMembershipController::class, 'create'])
        ->middleware(['auth', 'role:coach', 'coach.subscription'])
        ->name('client-memberships.create');

    Route::post('clients/{client}/assign-membership', [CoachClientMembershipController::class, 'store'])
        ->middleware(['auth', 'role:coach', 'coach.subscription'])
        ->name('client-memberships.store');

            // Rutas para registrar pagos de membresías
    Route::get('memberships/{membership}/register-payment', [ClientPaymentController::class, 'create'])
        ->middleware(['auth', 'role:coach', 'coach.subscription'])
        ->name('client-payments.create');

    Route::post('memberships/{membership}/register-payment', [ClientPaymentController::class, 'store'])
        ->middleware(['auth', 'role:coach', 'coach.subscription'])
        ->name('client-payments.store');

  Route::delete('client-memberships/{membership}', [CoachClientMembershipController::class, 'destroy'])
        ->name('client-memberships.destroy');



    // Rutas de Trainings
    Route::resource('trainings', TrainingController::class)
        ->middleware(['auth', 'role:coach', 'coach.subscription']);
});
Route::middleware(['auth', 'role:coach'])
    ->prefix('coach')
    ->name('coach.')
    ->group(function () {

        Route::resource('trainings', TrainingSessionController::class)
            ->parameters(['trainings' => 'training']); // opcional
    });
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
