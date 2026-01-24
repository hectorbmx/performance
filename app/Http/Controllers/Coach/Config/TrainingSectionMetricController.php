<?php

namespace App\Http\Controllers\Coach\Config;

use App\Http\Controllers\Controller;
use App\Models\MetricCatalog;
use App\Models\TrainingSection;
use App\Models\TrainingSectionMetric;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingSectionMetricController extends Controller
{
    private const INPUT_MODES = ['single', 'repeatable'];

    public function index(Request $request, TrainingSection $section)
    {
        $this->authorizeSection($request, $section);

        $items = TrainingSectionMetric::query()
            ->where('coach_id', $request->user()->id)
            ->where('training_section_id', $section->id)
            ->with('metric')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('coach.config.sections.metrics.index', compact('section', 'items'));
    }

    public function create(Request $request, TrainingSection $section)
    {
        $this->authorizeSection($request, $section);

        $coachId = $request->user()->id;

        $metrics = MetricCatalog::query()
            ->where('coach_id', $coachId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $inputModes = self::INPUT_MODES;

        return view('coach.config.sections.metrics.create', compact('section', 'metrics', 'inputModes'));
    }

    public function store(Request $request, TrainingSection $section)
    {
        $this->authorizeSection($request, $section);

        $coachId = $request->user()->id;

        $data = $request->validate([
            'metric_id' => [
                'required',
                'integer',
                Rule::exists('metric_catalogs', 'id')->where(fn ($q) => $q->where('coach_id', $coachId)),
            ],
            'label' => ['nullable', 'string', 'max:120'],
            'required' => ['nullable', 'boolean'],
            'input_mode' => ['required', Rule::in(self::INPUT_MODES)],
            'repeat_label' => ['nullable', 'string', 'max:50'],
            'unit_override' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ]);

        // regla: repeat_label solo aplica a repeatable
        if ($data['input_mode'] !== 'repeatable') {
            $data['repeat_label'] = null;
        } else {
            $data['repeat_label'] = $data['repeat_label'] ?? 'Intervalo';
        }

        $data['coach_id'] = $coachId;
        $data['training_section_id'] = $section->id;
        $data['required'] = (bool) ($data['required'] ?? true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        // evita duplicado por section (también hay unique en DB)
        $exists = TrainingSectionMetric::query()
            ->where('training_section_id', $section->id)
            ->where('metric_id', $data['metric_id'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['metric_id' => 'Esa métrica ya está asignada a esta sección.'])
                ->withInput();
        }

        TrainingSectionMetric::create($data);

        // Opcional UX: marcar accepts_results en sección
        if (!$section->accepts_results) {
            $section->update(['accepts_results' => true]);
        }

        return redirect()
            ->route('coach.config.sections.metrics.index', $section)
            ->with('success', 'Métrica agregada a la sección.');
    }

    public function edit(Request $request, TrainingSection $section, TrainingSectionMetric $sectionMetric)
    {
        $this->authorizeSection($request, $section);
        $this->authorizeSectionMetric($request, $section, $sectionMetric);

        $coachId = $request->user()->id;

        $metrics = MetricCatalog::query()
            ->where('coach_id', $coachId)
            ->orderBy('name')
            ->get();

        $inputModes = self::INPUT_MODES;

        return view('coach.config.sections.metrics.edit', compact('section', 'sectionMetric', 'metrics', 'inputModes'));
    }

    public function update(Request $request, TrainingSection $section, TrainingSectionMetric $sectionMetric)
    {
        $this->authorizeSection($request, $section);
        $this->authorizeSectionMetric($request, $section, $sectionMetric);

        $coachId = $request->user()->id;

        $data = $request->validate([
            'metric_id' => [
                'required',
                'integer',
                Rule::exists('metric_catalogs', 'id')->where(fn ($q) => $q->where('coach_id', $coachId)),
            ],
            'label' => ['nullable', 'string', 'max:120'],
            'required' => ['nullable', 'boolean'],
            'input_mode' => ['required', Rule::in(self::INPUT_MODES)],
            'repeat_label' => ['nullable', 'string', 'max:50'],
            'unit_override' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ]);

        if ($data['input_mode'] !== 'repeatable') {
            $data['repeat_label'] = null;
        } else {
            $data['repeat_label'] = $data['repeat_label'] ?? 'Intervalo';
        }

        $data['required'] = (bool) ($data['required'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        // evita duplicado por section (considerando cambio de metric_id)
        $exists = TrainingSectionMetric::query()
            ->where('training_section_id', $section->id)
            ->where('metric_id', $data['metric_id'])
            ->where('id', '!=', $sectionMetric->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['metric_id' => 'Esa métrica ya está asignada a esta sección.'])
                ->withInput();
        }

        $sectionMetric->update($data);

        return redirect()
            ->route('coach.config.sections.metrics.index', $section)
            ->with('success', 'Métrica de sección actualizada.');
    }

    public function destroy(Request $request, TrainingSection $section, TrainingSectionMetric $sectionMetric)
    {
        $this->authorizeSection($request, $section);
        $this->authorizeSectionMetric($request, $section, $sectionMetric);

        $sectionMetric->delete();

        // Si ya no quedan métricas, opcional: desmarcar accepts_results
        $stillHas = TrainingSectionMetric::query()
            ->where('training_section_id', $section->id)
            ->exists();

        if (!$stillHas && $section->accepts_results) {
            $section->update(['accepts_results' => false]);
        }

        return redirect()
            ->route('coach.config.sections.metrics.index', $section)
            ->with('success', 'Métrica eliminada de la sección.');
    }

    private function authorizeSection(Request $request, TrainingSection $section): void
    {
        // La sección pertenece a un training_session, y ese training_session es del coach autenticado
        $section->loadMissing('training');

        if ((int) $section->training->coach_id !== (int) $request->user()->id) {
            abort(404);
        }
    }

    private function authorizeSectionMetric(Request $request, TrainingSection $section, TrainingSectionMetric $sectionMetric): void
    {
        if ((int) $sectionMetric->coach_id !== (int) $request->user()->id) {
            abort(404);
        }

        if ((int) $sectionMetric->training_section_id !== (int) $section->id) {
            abort(404);
        }
    }
}
