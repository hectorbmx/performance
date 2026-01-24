<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Group;
use App\Models\Training;
use Illuminate\Http\Request;
use App\Models\TrainingSession;

class GroupController extends Controller
{
    public function index()
    {
        $q = trim(request('q', ''));

        $groups = Group::where('coach_id', auth()->id())
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->withCount(['clients', 'trainingAssignments'])
            ->orderBy('name')
            ->paginate(10)
            ->appends(['q' => $q]);

        return view('coach.groups.index', compact('groups', 'q'));
    }

    public function create()
    {
        return view('coach.groups.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $data['coach_id']  = auth()->id();
        $data['is_active'] = $request->boolean('is_active', true);

        // Validación amigable (además del unique en BD)
        $exists = Group::where('coach_id', $data['coach_id'])
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['name' => 'Ya existe un grupo con ese nombre.'])
                ->withInput();
        }

        $group = Group::create($data);

        return redirect()
            ->route('coach.groups.show', $group)
            ->with('success', 'Grupo creado correctamente.');
    }

    public function show(Group $group)
        {
            $coachId = auth()->id();
            abort_unless($group->coach_id === $coachId, 403, 'No tienes acceso a este grupo.');

            $assignedClientIds = $group->clients()->pluck('clients.id')->all();

            $availableClients = \App\Models\Client::where('coach_id', $coachId)
                ->when(count($assignedClientIds) > 0, function ($q) use ($assignedClientIds) {
                    $q->whereNotIn('id', $assignedClientIds);
                })
                ->orderBy('first_name')
                ->get();

            // ✅ Cabecera de entrenamientos (training_sessions)
            $trainings = TrainingSession::where('coach_id', $coachId)
                ->orderBy('title') // cambia a tu campo real (title/name)
                ->get();

            // $group->load([
            //     'clients' => fn ($q) => $q->orderBy('first_name'),
            //     'trainingAssignments.trainingSession' => fn ($q) => $q->orderBy('title'),
            // ]);
            $group->load(['trainingAssignments.trainingSession', 'clients']);


            $assignments = $group->trainingAssignments
                ->sortByDesc('scheduled_for')
                ->values();

            return view('coach.groups.show', compact('group', 'availableClients', 'trainings', 'assignments'));
        }

    public function edit(Group $group)
    {
        $coachId = auth()->id();
        abort_unless($group->coach_id === $coachId, 403, 'No tienes acceso a este grupo.');

        return view('coach.groups.edit', compact('group'));
    }

    public function update(Request $request, Group $group)
    {
        $coachId = auth()->id();
        abort_unless($group->coach_id === $coachId, 403, 'No tienes acceso a este grupo.');

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        // evitar duplicado por coach excluyendo el actual
        $exists = Group::where('coach_id', $coachId)
            ->where('name', $data['name'])
            ->where('id', '!=', $group->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['name' => 'Ya existe un grupo con ese nombre.'])
                ->withInput();
        }

        $group->update($data);

        return redirect()
            ->route('coach.groups.show', $group)
            ->with('success', 'Grupo actualizado correctamente.');
    }

    public function destroy(Group $group)
    {
        $coachId = auth()->id();
        abort_unless($group->coach_id === $coachId, 403, 'No tienes acceso a este grupo.');

        $group->delete();

        return redirect()
            ->route('coach.groups.index')
            ->with('success', 'Grupo eliminado correctamente.');
    }
    public function search(Request $request)
{
    $q = trim((string) $request->query('q', ''));

    if (mb_strlen($q) < 2) {
        return response()->json(['ok' => true, 'data' => []]);
    }

    $coachId = auth()->id();

    $groups = \App\Models\Group::query()
        ->where('coach_id', $coachId)
        ->where('is_active', 1)
        ->where('name', 'like', "%{$q}%")
        ->orderBy('name')
        ->limit(15)
        ->get(['id','name']);

    return response()->json([
        'ok' => true,
        'data' => $groups->map(fn ($g) => [
            'id' => (int) $g->id,
            'name' => $g->name,
        ])->values(),
    ]);
}

}
