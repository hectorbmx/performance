<?php

namespace App\Http\Controllers\Coach\Config;

use App\Http\Controllers\Controller;
use App\Models\SectionTypeCatalog;
use Illuminate\Http\Request;

class SectionTypeCatalogController extends Controller
{
    public function index(Request $request)
    {
        $coachId = $request->user()->id;

        $items = SectionTypeCatalog::query()
            ->where('coach_id', $coachId)
            ->orderBy('name')
            ->paginate(20);

        return view('coach.config.section-types.index', compact('items'));
    }

    public function create()
    {
        return view('coach.config.section-types.create');
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

        $exists = SectionTypeCatalog::where('coach_id', $coachId)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['name' => 'Ya existe un tipo de sección con ese nombre.'])
                ->withInput();
        }

        SectionTypeCatalog::create($data);

        return redirect()
            ->route('coach.config.section-types.index')
            ->with('success', 'Tipo de sección creado correctamente.');
    }

    public function edit(Request $request, SectionTypeCatalog $sectionType)
    {
        $this->authorizeCoach($request, $sectionType);

        return view('coach.config.section-types.edit', compact('sectionType'));
    }

    public function update(Request $request, SectionTypeCatalog $sectionType)
    {
        $this->authorizeCoach($request, $sectionType);

        $coachId = $request->user()->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $exists = SectionTypeCatalog::where('coach_id', $coachId)
            ->where('name', $data['name'])
            ->where('id', '!=', $sectionType->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['name' => 'Ya existe un tipo de sección con ese nombre.'])
                ->withInput();
        }

        $sectionType->update($data);

        return redirect()
            ->route('coach.config.section-types.index')
            ->with('success', 'Tipo de sección actualizado correctamente.');
    }

    public function destroy(Request $request, SectionTypeCatalog $sectionType)
    {
        $this->authorizeCoach($request, $sectionType);

        $sectionType->delete();

        return redirect()
            ->route('coach.config.section-types.index')
            ->with('success', 'Tipo de sección eliminado correctamente.');
    }

    private function authorizeCoach(Request $request, SectionTypeCatalog $sectionType): void
    {
        if ((int) $sectionType->coach_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
