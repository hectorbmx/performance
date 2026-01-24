<?php

namespace App\Http\Controllers\Coach\Config;

use App\Http\Controllers\Controller;
use App\Models\TrainingGoalCatalog;
use Illuminate\Http\Request;

class TrainingGoalCatalogController extends Controller
{
    public function index(Request $request)
    {
        $coachId = $request->user()->id;

        $items = TrainingGoalCatalog::query()
            ->where('coach_id', $coachId)
            ->orderBy('name')
            ->paginate(20);

        return view('coach.config.goals.index', compact('items'));
    }

    public function create()
    {
        return view('coach.config.goals.create');
    }

    public function store(Request $request)
    {
        $coachId = $request->user()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['coach_id'] = $coachId;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        $exists = TrainingGoalCatalog::where('coach_id', $coachId)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['name' => 'Ya existe un objetivo con ese nombre.'])
                ->withInput();
        }

        TrainingGoalCatalog::create($data);

        return redirect()
            ->route('coach.config.goals.index')
            ->with('success', 'Objetivo creado correctamente.');
    }

    public function edit(Request $request, TrainingGoalCatalog $goal)
    {
        $this->authorizeCoach($request, $goal);

        return view('coach.config.goals.edit', compact('goal'));
    }

    public function update(Request $request, TrainingGoalCatalog $goal)
    {
        $this->authorizeCoach($request, $goal);

        $coachId = $request->user()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $exists = TrainingGoalCatalog::where('coach_id', $coachId)
            ->where('name', $data['name'])
            ->where('id', '!=', $goal->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['name' => 'Ya existe un objetivo con ese nombre.'])
                ->withInput();
        }

        $goal->update($data);

        return redirect()
            ->route('coach.config.goals.index')
            ->with('success', 'Objetivo actualizado correctamente.');
    }

    public function destroy(Request $request, TrainingGoalCatalog $goal)
    {
        $this->authorizeCoach($request, $goal);

        $goal->delete();

        return redirect()
            ->route('coach.config.goals.index')
            ->with('success', 'Objetivo eliminado correctamente.');
    }

    private function authorizeCoach(Request $request, TrainingGoalCatalog $goal): void
    {
        if ((int) $goal->coach_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
