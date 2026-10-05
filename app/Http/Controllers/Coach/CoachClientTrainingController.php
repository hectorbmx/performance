<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Group;
use App\Models\GroupTrainingAssignment;
use App\Models\TrainingAssignment;
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
        $monthStart = $currentMonth->copy()->startOfMonth();
        $monthEnd = $currentMonth->copy()->endOfMonth();
        $completedAssignmentsThisMonth = TrainingAssignment::query()
            ->select('training_assignments.*')
            ->join('training_sessions', 'training_sessions.id', '=', 'training_assignments.training_session_id')
            ->where('training_sessions.coach_id', $request->user()->id)
            ->where('training_assignments.client_id', $client->id)
            ->where('training_assignments.status', 'completed')
            ->where(function ($completedQuery) use ($monthStart, $monthEnd) {
                $completedQuery
                    ->whereBetween('training_assignments.scheduled_for', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->orWhere(function ($fallbackQuery) use ($monthStart, $monthEnd) {
                        $fallbackQuery
                            ->whereNull('training_assignments.scheduled_for')
                            ->whereBetween('training_sessions.scheduled_at', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                    });
            })
            ->get();

        $trainingSecondsThisMonth = $completedAssignmentsThisMonth
            ->filter(fn ($assignment) => $assignment->started_at && $assignment->completed_at && $assignment->completed_at->gte($assignment->started_at))
            ->sum(fn ($assignment) => $assignment->started_at->diffInSeconds($assignment->completed_at));

        $clientTrainingKpis = [
            'assigned_this_month' => $trainings
                ->filter(fn ($training) => $training->scheduled_at?->betweenIncluded($monthStart, $monthEnd))
                ->count(),
            'completed_this_month' => $completedAssignmentsThisMonth->count(),
            'training_hours' => round($trainingSecondsThisMonth / 3600, 1),
        ];

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
            'clientTrainingKpis' => $clientTrainingKpis,
        ]);
    }
}
