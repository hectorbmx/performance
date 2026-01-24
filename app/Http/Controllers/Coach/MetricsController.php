<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TrainingMetric;
use App\Models\CoachTrainingMetric;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class MetricsController extends Controller
{
    /**
     * Mostrar métricas disponibles y configuración del coach
     */
    public function index(Request $request)
{
    $coachId = auth()->id();

    // Métricas visibles para el coach: globales + propias
    $metrics = TrainingMetric::query()
        ->visibleToCoach($coachId)
        ->where('is_active', 1)
        ->orderByRaw('CASE WHEN coach_id IS NULL THEN 0 ELSE 1 END') // globales primero
        ->orderBy('name')
        ->get();

    // Configuración del coach (pivot)
    $coachMetrics = CoachTrainingMetric::query()
        ->where('coach_id', $coachId)
        ->get()
        ->keyBy('training_metric_id');

    // Merge catálogo + config del coach
    $items = $metrics->map(function ($metric) use ($coachMetrics, $coachId) {
        $config = $coachMetrics->get($metric->id);

        return [
            'id' => $metric->id,
            'coach_id' => $metric->coach_id, // null si es global
            'is_owner' => (int) $metric->coach_id === (int) $coachId,

            'code' => $metric->code,
            'name' => $metric->name,
            'unit' => $metric->unit,
            'type' => $metric->type,
            'is_active' => (bool) $metric->is_active,

            // Config del coach
            'enabled'     => (bool) $config,
            'is_required' => (bool) ($config?->is_required ?? false),
            'sort_order'  => $config?->sort_order ?? 0,
        ];
    });

    return view('coach.config.settings.coach-metrics.index', [
        'items' => $items,
    ]);
}


    /**
     * Guardar configuración de métricas del coach (batch)
     */
    public function update(Request $request)
    {
        $coachId = auth()->id();

        $data = $request->validate([
            'metrics' => ['array'],
            'metrics.*.id'          => ['required','integer','exists:training_metrics,id'],
            'metrics.*.enabled'     => ['required','boolean'],
            'metrics.*.is_required' => ['nullable','boolean'],
            'metrics.*.sort_order'  => ['nullable','integer','min:0','max:100'],
        ]);

        foreach ($data['metrics'] as $metric) {

            if ($metric['enabled']) {
                CoachTrainingMetric::updateOrCreate(
                    [
                        'coach_id' => $coachId,
                        'training_metric_id' => $metric['id'],
                    ],
                    [
                        'is_required' => $metric['is_required'] ?? false,
                        'sort_order'  => $metric['sort_order'] ?? 0,
                    ]
                );
            } else {
                // Si se desactiva, eliminamos el pivot
                CoachTrainingMetric::where('coach_id', $coachId)
                    ->where('training_metric_id', $metric['id'])
                    ->delete();
            }
        }

        return redirect()
            ->route('coach.config.settings.coach-metrics.index')
            ->with('success', 'Configuración de métricas actualizada correctamente.');
    }
public function storeCatalog(Request $request)
{
    $coachId = auth()->id();

    $data = $request->validate([
        'name' => ['required','string','max:120'],
        'code' => ['nullable','string','max:80'],
        'unit' => ['nullable','string','max:20'],
        'type' => ['nullable','string','max:30'],
        'is_active' => ['nullable','boolean'],
    ]);

    // Si no mandan code, generamos uno estable desde el nombre
    $code = $data['code'] ?? Str::slug($data['name'], '_');
    $code = substr($code, 0, 80);

    // Evitar duplicados por coach (mismo code)
    $exists = TrainingMetric::query()
        ->where('coach_id', $coachId)
        ->where('code', $code)
        ->exists();

    if ($exists) {
        return back()
            ->withErrors(['code' => 'Ya existe una métrica con este código para tu cuenta.'])
            ->withInput();
    }

    TrainingMetric::create([
        'coach_id' => $coachId,
        'code' => $code,
        'name' => $data['name'],
        'unit' => $data['unit'] ?? null,
        'type' => $data['type'] ?? 'custom',
        'is_active' => array_key_exists('is_active', $data) ? (int) (bool) $data['is_active'] : 1,
    ]);

    return redirect()
        ->route('coach.config.settings.coach-metrics.index')
        ->with('success', 'Métrica creada correctamente.');
}

public function updateCatalog(Request $request, TrainingMetric $metric)
{
    $coachId = auth()->id();

    // Seguridad: solo puede editar sus métricas
    if ((int) $metric->coach_id !== (int) $coachId) {
        abort(403);
    }

    $data = $request->validate([
        'name' => ['required','string','max:120'],
        'code' => ['required','string','max:80',
            Rule::unique('training_metrics', 'code')->where(function ($q) use ($coachId, $metric) {
                return $q->where('coach_id', $coachId)->where('id', '!=', $metric->id);
            })
        ],
        'unit' => ['nullable','string','max:20'],
        'type' => ['nullable','string','max:30'],
        'is_active' => ['nullable','boolean'],
    ]);

    $metric->update([
        'name' => $data['name'],
        'code' => $data['code'],
        'unit' => $data['unit'] ?? null,
        'type' => $data['type'] ?? 'custom',
        'is_active' => array_key_exists('is_active', $data) ? (int) (bool) $data['is_active'] : $metric->is_active,
    ]);

    return redirect()
        ->route('coach.config.settings.coach-metrics.index')
        ->with('success', 'Métrica actualizada correctamente.');
}

public function destroyCatalog(TrainingMetric $metric)
{
    $coachId = auth()->id();

    if ((int) $metric->coach_id !== (int) $coachId) {
        abort(403);
    }

    // En vez de borrar físicamente, desactivamos (más seguro por historial)
    $metric->update(['is_active' => 0]);

    // Opcional: también quitarla del pivot del coach
    CoachTrainingMetric::where('coach_id', $coachId)
        ->where('training_metric_id', $metric->id)
        ->delete();

    return redirect()
        ->route('coach.config.settings.coach-metrics.index')
        ->with('success', 'Métrica desactivada correctamente.');
}

}
