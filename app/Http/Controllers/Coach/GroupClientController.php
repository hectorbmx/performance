<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Group;
use Illuminate\Http\Request;

class GroupClientController extends Controller
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

    /**
     * Asignar uno o varios clientes a un grupo.
     */
   public function store(Request $request, Group $group)
{
    $coachId = auth()->id();

    // Seguridad: el grupo debe ser del coach
    abort_unless($group->coach_id === $coachId, 403, 'No tienes acceso a este grupo.');

    // Validación
    $data = $request->validate([
        'client_ids'   => ['required', 'array', 'min:1'],
        'client_ids.*' => ['integer', 'distinct'],
    ]);

    $clientIds = $data['client_ids'];

    // Validar que los clientes pertenezcan al coach
    $validClientIds = Client::where('coach_id', $coachId)
        ->whereIn('id', $clientIds)
        ->pluck('id')
        ->all();

    if (count($validClientIds) !== count($clientIds)) {
        return back()->with('error', 'Uno o más clientes no son válidos para este entrenador.');
    }

    // Asignar sin duplicar
    $group->clients()->syncWithoutDetaching($validClientIds);

    return back()->with('success', 'Clientes asignados al grupo correctamente.');
}


    /**
     * Quitar un cliente de un grupo.
     */
    public function destroy(Group $group, Client $client)
    {
        $coachId = auth()->id();

        // 1) Seguridad: grupo del coach
        abort_unless($group->coach_id === $coachId, 403, 'No tienes acceso a este grupo.');

        // 2) Seguridad: cliente del coach
        abort_unless($client->coach_id === $coachId, 403, 'No tienes acceso a este cliente.');

        // 3) Detach (si no estaba, no truena)
        $group->clients()->detach($client->id);

        return back()->with('success', 'Cliente removido del grupo correctamente.');
    }
    public function show(\App\Models\Group $group)
{
    $coachId = auth()->id();
    abort_unless($group->coach_id === $coachId, 403);

    // Clientes disponibles para asignar (solo del coach y NO asignados)
    $assignedClientIds = $group->clients()->pluck('clients.id')->all();

    $availableClients = \App\Models\Client::where('coach_id', $coachId)
        ->when(count($assignedClientIds) > 0, function ($q) use ($assignedClientIds) {
            $q->whereNotIn('id', $assignedClientIds);
        })
        ->orderBy('first_name')
        ->get();

    // Entrenamientos disponibles para asignar (solo del coach)
    // Ajusta 'coach_id' si tu tabla trainings usa otro campo.
    $trainings = \App\Models\Training::where('coach_id', $coachId)
        ->orderBy('name')
        ->get();

    // Cargar relaciones del grupo
    $group->load([
        'clients' => fn ($q) => $q->orderBy('first_name'),
        'trainingAssignments.training' => fn ($q) => $q->orderBy('name'),
    ]);

    // Opcional: ordenar asignaciones por fecha (si no lo haces en la relación)
    $assignments = $group->trainingAssignments
        ->sortByDesc('scheduled_for')
        ->values();

    return view('coach.groups.show', compact('group', 'availableClients', 'trainings', 'assignments'));
}

}
