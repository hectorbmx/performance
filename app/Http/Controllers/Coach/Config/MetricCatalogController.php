<?php

namespace App\Http\Controllers\Coach\Config;

use App\Http\Controllers\Controller;
use App\Models\MetricCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetricCatalogController extends Controller
{
    private const VALUE_KINDS = ['duration', 'integer', 'decimal', 'boolean', 'text'];

    public function index(Request $request)
    {
        $coachId = $request->user()->id;

        $items = MetricCatalog::query()
            ->where('coach_id', $coachId)
            ->orderBy('name')
            ->paginate(20);

        return view('coach.config.metrics.index', compact('items'));
    }

    public function create()
    {
        $valueKinds = self::VALUE_KINDS;

        return view('coach.config.metrics.create', compact('valueKinds'));
    }

    public function store(Request $request)
    {
        $coachId = $request->user()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'value_kind' => ['required', Rule::in(self::VALUE_KINDS)],
            'unit_default' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['coach_id'] = $coachId;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        $exists = MetricCatalog::where('coach_id', $coachId)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['name' => 'Ya existe una métrica con ese nombre.'])
                ->withInput();
        }

        MetricCatalog::create($data);

        return redirect()
            ->route('coach.config.metrics.index')
            ->with('success', 'Métrica creada correctamente.');
    }

    public function edit(Request $request, MetricCatalog $metric)
    {
        $this->authorizeCoach($request, $metric);

        $valueKinds = self::VALUE_KINDS;

        return view('coach.config.metrics.edit', compact('metric', 'valueKinds'));
    }

    public function update(Request $request, MetricCatalog $metric)
    {
        $this->authorizeCoach($request, $metric);

        $coachId = $request->user()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'value_kind' => ['required', Rule::in(self::VALUE_KINDS)],
            'unit_default' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $exists = MetricCatalog::where('coach_id', $coachId)
            ->where('name', $data['name'])
            ->where('id', '!=', $metric->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['name' => 'Ya existe una métrica con ese nombre.'])
                ->withInput();
        }

        $metric->update($data);

        return redirect()
            ->route('coach.config.metrics.index')
            ->with('success', 'Métrica actualizada correctamente.');
    }

    public function destroy(Request $request, MetricCatalog $metric)
    {
        $this->authorizeCoach($request, $metric);

        $metric->delete();

        return redirect()
            ->route('coach.config.metrics.index')
            ->with('success', 'Métrica eliminada correctamente.');
    }

    private function authorizeCoach(Request $request, MetricCatalog $metric): void
    {
        if ((int) $metric->coach_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
