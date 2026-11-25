<?php

namespace App\Http\Controllers\Api\V1\App\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\TrainingAssignment;
use App\Models\TrainingSession;
use App\Models\UserApp;
use App\Models\WorkoutExecution;
use App\Services\ClientMembershipAccessService;
use App\Services\WorkoutPrescriptionSnapshot;
use App\Services\WorkoutSetRecords;
use App\Services\WorkoutExecutionCompletion;
use App\Services\WorkoutMeasuredSummary;
use App\Services\WorkoutOwnership;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkoutExecutionController extends Controller
{
    public function store(Request $request, ClientMembershipAccessService $access, WorkoutPrescriptionSnapshot $snapshots)
    {
        $client = $this->activeClient($request);
        $data = $request->validate([
            'client_execution_id' => ['required', 'uuid'],
            'training_assignment_id' => ['required', 'integer', 'min:1'],
        ]);
        $requestId = strtolower($data['client_execution_id']);
        $fingerprint = hash('sha256', 'assignment:'.(int) $data['training_assignment_id']);

        [$execution, $created] = DB::transaction(function () use ($request, $client, $data, $requestId, $fingerprint, $access, $snapshots) {
            // Serialize starts for one athlete, including concurrent retries.
            $lockedClient = Client::query()->lockForUpdate()->findOrFail($client->id);
            abort_unless($lockedClient->is_active && (int) $lockedClient->coach_id === (int) $client->coach_id, 403);
            $existing = WorkoutExecution::query()
                ->where('client_id', $client->id)
                ->where('client_execution_id', $requestId)
                ->first();
            if ($existing) {
                abort_if($existing->data_scope===WorkoutOwnership::PERSONAL,409,'Este identificador pertenece a otra clase de sesiÃ³n.');
                abort_unless((int) $existing->coach_id === (int) $lockedClient->coach_id, 404);
                abort_unless(hash_equals($existing->start_fingerprint, $fingerprint), 409, 'UUID reutilizado para otra rutina.');
                return [$existing, false];
            }

            abort_unless(config('workouts.executions_enabled'), 403, 'Nuevas ejecuciones deshabilitadas.');
            $membership = $access->forUserApp($request->user());
            abort_unless($membership->canAccessService, 403, $membership->message ?? 'MembresÃ­a no vigente.');

            $assignment = TrainingAssignment::query()
                ->where('client_id', $client->id)
                ->findOrFail($data['training_assignment_id']);
            // Coach editors update the parent before children in their transaction.
            $training = TrainingSession::query()->lockForUpdate()->findOrFail($assignment->training_session_id);
            $assignment = TrainingAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            abort_unless((int) $assignment->client_id === (int) $client->id, 404);
            abort_unless((int) $assignment->training_session_id === (int) $training->id, 409, 'La asignaciÃ³n cambiÃ³; vuelve a iniciar.');
            abort_unless((int) $training->coach_id === (int) $lockedClient->coach_id, 404);
            abort_if(in_array($assignment->status, ['cancelled', 'skipped'], true), 422, 'AsignaciÃ³n no disponible.');

            $content = $snapshots->capture($training);
            $execution = WorkoutExecution::query()->create([
                'uuid' => (string) Str::uuid(),
                'client_execution_id' => $requestId,
                'client_id' => $client->id,
                'coach_id' => $lockedClient->coach_id,
                'data_scope'=>WorkoutOwnership::ASSIGNED,
                'training_session_id' => $training->id,
                'training_assignment_id' => $assignment->id,
                'start_fingerprint' => $fingerprint,
                'status' => 'in_progress',
                'revision' => 1,
                'started_at' => now(),
            ]);
            $execution->prescription()->create([
                'schema_version' => 1,
                'content' => $content,
                'content_hash' => hash('sha256', json_encode($content, JSON_THROW_ON_ERROR)),
                'created_at' => now(),
            ]);
            return [$execution, true];
        }, 3);

        return response()->json(['ok' => true, 'data' => $this->payload($execution)], $created ? 201 : 200);
    }

    public function index(Request $request)
    {
        $ownership=app(WorkoutOwnership::class);$actor=$ownership->athlete($request);
        $data = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:50']]);
        // List headers only; full immutable prescription is available in show.
        $page = $ownership->executions($actor)->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);
        return response()->json([
            'ok' => true,
            'data' => $page->getCollection()->map(fn ($execution) => $this->header($execution)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, string $execution)
    {
        $ownership=app(WorkoutOwnership::class);
        $item = $ownership->executions($ownership->athlete($request))->where('uuid',$execution)->firstOrFail();
        return response()->json(['ok' => true, 'data' => $this->payload($item)]);
    }

    public function saveSet(Request $request, string $execution, string $set, WorkoutSetRecords $records)
    {
        app(WorkoutOwnership::class)->athlete($request);
        $validator = Validator::make($request->all(), [
            'operation_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:0', 'max:2147483646'],
            'status' => ['required', 'in:completed,failed,skipped'],
            'type' => ['required', 'in:warmup,work'],
            'load_mode' => ['required', 'in:total_barbell,per_dumbbell,external_total,machine_indicated,unknown'],
            'actual_reps' => ['present', 'nullable', 'integer', 'min:0', 'max:1000'],
            'actual_load' => ['present', 'nullable', 'numeric', 'min:0', 'max:10000', 'regex:/^\d{1,5}(?:\.\d{1,2})?$/'],
            'load_unit' => ['present', 'nullable', 'in:kg,lb'],
            'actual_rir' => ['present', 'nullable', 'numeric', 'min:0', 'max:10', 'regex:/^\d{1,2}(?:\.\d)?$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'performed_at' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/'],
        ]);
        $validator->after(function ($validator) use ($request) {
            $status = $request->input('status');
            if (in_array($status, ['completed', 'failed'], true) && $request->input('actual_reps') === null) {
                $validator->errors()->add('actual_reps', 'Captura las repeticiones realizadas.');
            }
            if ($status === 'completed' && $request->input('actual_reps') !== null && (int) $request->input('actual_reps') < 1) {
                $validator->errors()->add('actual_reps', 'Una serie completada requiere al menos una repeticiÃ³n.');
            }
            if ($request->input('actual_load') !== null) {
                if ($request->input('load_mode') === 'unknown') {
                    $validator->errors()->add('load_mode', 'El piloto admite carga explÃ­cita solo como peso total de barra.');
                }
                if ($request->input('load_unit') === null) {
                    $validator->errors()->add('load_unit', 'Selecciona kg o lb.');
                }
            } elseif ($request->input('load_unit') !== null) {
                $validator->errors()->add('load_unit', 'No indiques unidad sin carga registrada.');
            }
            if ($status === 'skipped') {
                foreach (['actual_reps', 'actual_load', 'actual_rir', 'load_unit'] as $field) {
                    if ($request->input($field) !== null) $validator->errors()->add($field, 'Una serie omitida no registra valores realizados.');
                }
            }
        });
        $result = $records->save($request->user(), $execution, $set, $validator->validate());
        $status = $result['_status'] ?? 200;
        unset($result['_status']);
        return response()->json($result, $status);
    }

    public function complete(Request $request, string $execution, WorkoutExecutionCompletion $completion)
    {
        app(WorkoutOwnership::class)->athlete($request);
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'expected_execution_revision' => ['required', 'integer', 'min:1', 'max:2147483646'],
        ]);
        $result = $completion->complete($request->user(), $execution, $data);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);
        return response()->json($result, $status);
    }

    public function setRevisions(Request $request, string $execution, string $set, WorkoutSetRecords $records)
    {
        $ownership=app(WorkoutOwnership::class);
        $item = $ownership->executions($ownership->athlete($request))->where('uuid',$execution)->firstOrFail();
        return response()->json(['ok' => true, 'data' => $records->revisions($item, $set)]);
    }

    private function activeClient(Request $request): Client
    {
        $user = $request->user();
        abort_unless($user instanceof UserApp && $user->is_active, 403, 'Cuenta de atleta activa requerida.');
        $client = $user->client;
        abort_unless($client && $client->is_active, 403, 'Atleta inactivo.');
        return $client;
    }

    private function header(WorkoutExecution $execution): array
    {
        return app(\App\Services\WorkoutExecutionPresenter::class)->header($execution);
    }
    private function payload(WorkoutExecution $execution): array
    {
        return app(\App\Services\WorkoutExecutionPresenter::class)->detail($execution);
    }
}
