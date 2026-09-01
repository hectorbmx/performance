<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\GroupTrainingAssignment;
use App\Models\TrainingSession;
use Illuminate\Http\Request;

class CoachClientTrainingController extends Controller
{
    public function index(Request $request, Client $client)
    {
        // Scope por coach
        if ((int) $client->coach_id !== (int) $request->user()->id) {
            abort(404);
        }

        // Reusar tu misma lógica de vista (list/calendar)
        $view = $request->get('view', 'calendar'); // default calendario aquí
        $date = $request->get('date'); // opcional, por si quieres abrir en un día

        $clientGroupIds = $client->groups()->pluck('groups.id')->all();

        $query = TrainingSession::query()
            ->where('coach_id', $request->user()->id)
            ->where(function ($q) use ($client, $clientGroupIds) {
                $q->whereHas('assignedClients', function ($clientQuery) use ($client) {
                    $clientQuery->where('clients.id', $client->id);
                });

                if (!empty($clientGroupIds)) {
                    $q->orWhereIn('id', GroupTrainingAssignment::query()
                        ->select('training_session_id')
                        ->whereIn('group_id', $clientGroupIds));
                }
            })
            ->withCount('sections')
            ->distinct()
            ->orderBy('scheduled_at', 'desc');

        // Para modo lista puedes paginar; para calendario normalmente traes el mes visible.
        $trainings = $query->get();

        return view('coach.clients.trainings.index', [
            'client' => $client,
            'trainings' => $trainings,
            'viewMode' => $view,
            'date' => $date,
        ]);
    }
}
