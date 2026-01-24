<?php

namespace App\Http\Controllers\Coach\Config;

use App\Http\Controllers\Controller;
use App\Models\TrainingTypeCatalog;
use Illuminate\Http\Request;

class TrainingTypeCatalogController extends Controller
{
    public function index(Request $request)
    {
        $coachId = $request->user()->id;

       $types = TrainingTypeCatalog::query()
            ->where('coach_id', $coachId)
            ->orderBy('name')
            ->paginate(20);

        return view('coach.config.types.index', compact('types'));
    }

    public function create()
    {
        return view('coach.config.types.create');
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

        // Unicidad por coach (también existe unique en DB)
        $exists = TrainingTypeCatalog::where('coach_id', $coachId)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['name' => 'Ya existe un tipo con ese nombre.'])
                ->withInput();
        }

        TrainingTypeCatalog::create($data);

        return redirect()
            ->route('coach.config.types.index')
            ->with('success', 'Tipo creado correctamente.');
    }

    public function edit(Request $request, TrainingTypeCatalog $type)
    {
        $this->authorizeCoach($request, $type);

        return view('coach.config.types.edit', compact('type'));
    }

    public function update(Request $request, TrainingTypeCatalog $type)
    {
        $this->authorizeCoach($request, $type);

        $coachId = $request->user()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $exists = TrainingTypeCatalog::where('coach_id', $coachId)
            ->where('name', $data['name'])
            ->where('id', '!=', $type->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['name' => 'Ya existe un tipo con ese nombre.'])
                ->withInput();
        }

        $type->update($data);

        return redirect()
            ->route('coach.config.types.index')
            ->with('success', 'Tipo actualizado correctamente.');
    }

    public function destroy(Request $request, TrainingTypeCatalog $type)
    {
        $this->authorizeCoach($request, $type);

        $type->delete();

        return redirect()
            ->route('coach.config.types.index')
            ->with('success', 'Tipo eliminado correctamente.');
    }

    private function authorizeCoach(Request $request, TrainingTypeCatalog $type): void
    {
        if ((int) $type->coach_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
