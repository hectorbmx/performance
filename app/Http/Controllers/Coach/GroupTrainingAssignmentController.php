<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupTrainingAssignment;
use App\Models\TrainingSession; // Modelo de training_sessions
use Illuminate\Http\Request;

class GroupTrainingAssignmentController extends Controller
{
    public function store(Request $request, Group $group)
    {
        $coachId = auth()->id();

        // 1) Seguridad: el grupo debe ser del coach
        abort_unless($group->coach_id === $coachId, 403, 'No tienes acceso a este grupo.');

        // 2) Validación
        $data = $request->validate([
            'training_session_id'   => ['required', 'integer'],
            'scheduled_for' => ['required', 'date'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        // 3) Validar que la cabecera (training_session) pertenezca al coach
        $session = TrainingSession::where('coach_id', $coachId)
            ->where('id', $data['training_session_id'])
            ->first();

        if (!$session) {
            return back()->with('error', 'Entrenamiento inválido o no pertenece a tu cuenta.');
        }

        // 4) Evitar duplicado: mismo grupo + mismo entrenamiento + misma fecha
        $exists = GroupTrainingAssignment::where('group_id', $group->id)
            ->where('training_session_id', $data['training_session_id'])
            ->whereDate('scheduled_for', $data['scheduled_for'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Ese entrenamiento ya está asignado a este grupo en esa fecha.');
        }

        // 5) Crear asignación
        GroupTrainingAssignment::create([
            'group_id'      => $group->id,
            'training_session_id'   => $data['training_session_id'],   // apunta a training_sessions
            'scheduled_for' => $data['scheduled_for'],
            'notes'         => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Entrenamiento asignado correctamente.');
    }

    public function destroy(Group $group, GroupTrainingAssignment $assignment)
    {
        $coachId = auth()->id();

        // 1) Seguridad: grupo del coach
        abort_unless($group->coach_id === $coachId, 403, 'No tienes acceso a este grupo.');

        // 2) Seguridad: la asignación debe pertenecer al grupo
        abort_unless($assignment->group_id === $group->id, 404);

        $assignment->delete();

        return back()->with('success', 'Asignación eliminada correctamente.');
    }
}
