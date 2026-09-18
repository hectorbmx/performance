<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Group;
use App\Models\GroupTrainingAssignment;
use App\Models\TrainingSession;
use Carbon\Carbon;
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
            ->with([
                'assignments.client:id,first_name,last_name,email',
            ])
            ->withCount('sections')
            ->distinct()
            ->orderBy('scheduled_at', 'desc');

        $trainings = $query->get();
        $trainingIds = $trainings->pluck('id')->all();

        $groupAssignments = GroupTrainingAssignment::query()
            ->with('group:id,name')
            ->whereIn('training_session_id', $trainingIds)
            ->get()
            ->groupBy('training_session_id');

        $copyClients = Client::query()
            ->where('coach_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        $copyGroups = Group::query()
            ->where('coach_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $month = $request->get('month', now()->format('Y-m'));
        $currentMonth = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();

        return view('coach.clients.trainings.index', [
            'client' => $client,
            'trainings' => $trainings,
            'viewMode' => $view,
            'date' => $date,
            'month' => $month,
            'currentMonth' => $currentMonth,
            'groupAssignments' => $groupAssignments,
            'copyClients' => $copyClients,
            'copyGroups' => $copyGroups,
        ]);
    }
}
