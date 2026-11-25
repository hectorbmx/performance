<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Models\UserApp;
use App\Models\WorkoutExecution;
use App\Models\WorkoutExercise;
use App\Models\WorkoutMaxRecord;
use App\Services\WorkoutMaxes;
use App\Services\WorkoutOwnership;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkoutMaxController extends Controller
{
    private function athlete(Request $request): UserApp {
        return app(WorkoutOwnership::class)->athlete($request);
    }
    private function coachClient(Request $request, Client $client): Client {
        abort_unless($request->user() instanceof User && (int) $client->coach_id === (int) $request->user()->id, 404); return $client;
    }
    private function exercise(Request $request, Client|UserApp $client,bool $writing=false): WorkoutExercise {
        $data = $request->validate(['exercise_id' => ['required', 'integer', 'min:1']]);
        return app(WorkoutOwnership::class)->exercise($client,(int)$data['exercise_id'],$writing);
    }
    private function records(Request $request, Client|UserApp $client, WorkoutMaxes $maxes) {
        $exercise = $this->exercise($request, $client);
        return response()->json(['ok' => true, 'data' => $maxes->records($client, $exercise)->paginate(30), 'basis' => $maxes->basis($client, $exercise)]);
    }
    public function athleteIndex(Request $request, WorkoutMaxes $maxes) { return $this->records($request, $this->athlete($request), $maxes); }
    public function coachIndex(Request $request, Client $client, WorkoutMaxes $maxes) { return $this->records($request, $this->coachClient($request, $client), $maxes); }
    public function athleteStore(Request $request, WorkoutMaxes $maxes) { return $this->tested($request, $this->athlete($request), $maxes); }
    public function coachStore(Request $request, Client $client, WorkoutMaxes $maxes) { return $this->tested($request, $this->coachClient($request, $client), $maxes); }
    private function tested(Request $request, Client|UserApp $client, WorkoutMaxes $maxes) {
        $exercise = $this->exercise($request, $client,true);
        abort_unless($exercise->is_active && $exercise->load_mode === 'total_barbell', 422, 'RM disponible solo para carga total de barra compatible.');
        $data = $request->validate([
            'value' => ['required', 'numeric', 'gt:0', 'max:10000', 'regex:/^\d{1,5}(?:\.\d{1,2})?$/'],
            'unit' => ['required', 'in:kg,lb'], 'measured_at' => ['required', 'date', 'before_or_equal:now'],
            'single_rep_confirmed' => ['required', 'accepted'], 'notes' => ['nullable', 'string', 'max:2000'],
            'supersedes_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $actor = $request->user();
        $record = DB::transaction(function () use ($data, $actor, $client, $exercise, $maxes) {
            $ownership=app(WorkoutOwnership::class);$ownership->lock($client);
            if (!empty($data['supersedes_id'])) {
                $old = $ownership->data(WorkoutMaxRecord::query(),$client)->where('kind', 'tested')
                    ->where('workout_exercise_id', $exercise->id)->lockForUpdate()->findOrFail($data['supersedes_id']);
                abort_if(WorkoutMaxRecord::where('supersedes_id', $old->id)->exists(), 409, 'Este RM ya fue corregido; usa su corrección vigente.');
            }
            return WorkoutMaxRecord::create([
                'workout_exercise_id' => $exercise->id,
                'kind' => 'tested', 'method' => 'single_rep_reported', 'confidence' => 'declared', 'load_mode' => 'total_barbell',
                'value_original' => $data['value'], 'unit_original' => $data['unit'], 'value_kg' => $maxes->kg($data['value'], $data['unit']),
                'measured_at' => Carbon::parse($data['measured_at'])->utc(), 'source' => $actor instanceof UserApp ? 'athlete_reported' : 'coach_reported',
                'user_app_id' => $actor instanceof UserApp ? $actor->id : null, 'recorded_by_coach_id' => $actor instanceof User ? $actor->id : null,
                'notes' => $data['notes'] ?? null, 'supersedes_id' => $data['supersedes_id'] ?? null, 'created_at' => now()->utc(),
            ]+$ownership->fields($client));
        });
        return response()->json(['ok' => true, 'data' => $record], 201);
    }
    public function athleteEstimate(Request $request, WorkoutMaxes $maxes) { return $this->estimate($request, $this->athlete($request), $maxes); }
    public function coachEstimate(Request $request, Client $client, WorkoutMaxes $maxes) { return $this->estimate($request, $this->coachClient($request, $client), $maxes); }
    private function estimate(Request $request, Client|UserApp $client, WorkoutMaxes $maxes) {
        $exercise = $this->exercise($request, $client,true);
        abort_unless($exercise->load_mode === 'total_barbell',422,'Estimación disponible solo para barra compatible.');
        $data = $request->validate(['execution_uuid' => ['required', 'uuid'], 'set_uuid' => ['required', 'uuid']]);
        $execution = app(WorkoutOwnership::class)->data(WorkoutExecution::query(),$client)
            ->when($client instanceof Client,fn($q)=>$q->where('data_scope',WorkoutOwnership::ASSIGNED))->where('uuid',$data['execution_uuid'])->firstOrFail();
        $matches = false;
        foreach ($execution->current_prescription->content['sections'] as $section) foreach ($section['exercises'] as $item) {
            if ((int) ($item['workout_exercise_id'] ?? 0) !== (int) $exercise->id) continue;
            foreach ($item['sets'] as $set) if ($set['uuid'] === strtolower($data['set_uuid'])) $matches = true;
        }
        abort_unless($matches, 422, 'La serie no corresponde al ejercicio exacto.');
        $record = DB::transaction(function () use ($execution, $data, $client, $exercise, $request, $maxes) {
            $ownership=app(WorkoutOwnership::class);$ownership->lock($client);
            $source = DB::table('workout_execution_sets')->where('workout_execution_id', $execution->id)->where('set_uuid', strtolower($data['set_uuid']))->lockForUpdate()->first();
            abort_unless($source && $source->load_mode === 'total_barbell', 422, 'No hay registro de barra compatible.');
            $estimate = $maxes->estimate($source); abort_unless($estimate, 422, 'Datos insuficientes para estimar RM con este método.');
            $actor = $request->user();
            $existing=WorkoutMaxRecord::where('source_set_id',$source->id)->where('source_revision',$source->revision)->first();
            if($existing){abort_unless($ownership->data(WorkoutMaxRecord::query(),$client)->whereKey($existing->id)->exists(),409,'La evidencia anterior requiere revisar su propiedad.');return $existing;}
            return WorkoutMaxRecord::firstOrCreate(['source_set_id' => $source->id, 'source_revision' => $source->revision], [
                'workout_exercise_id' => $exercise->id,
                'kind' => 'estimated', 'method' => $estimate['method'], 'confidence' => $estimate['confidence'], 'load_mode' => 'total_barbell',
                'value_original' => number_format((float) $estimate['value_kg'], 2, '.', ''), 'unit_original' => 'kg', 'value_kg' => $estimate['value_kg'],
                'measured_at' => $source->performed_at, 'source' => 'set_derived', 'evidence' => ['actual_load' => $source->actual_load,
                    'load_unit' => $source->load_unit, 'actual_reps' => $source->actual_reps, 'actual_rir' => $source->actual_rir],
                'user_app_id' => $actor instanceof UserApp ? $actor->id : null, 'recorded_by_coach_id' => $actor instanceof User ? $actor->id : null, 'created_at' => now()->utc(),
            ]+$ownership->fields($client));
        });
        return response()->json(['ok' => true, 'data' => $record], $record->wasRecentlyCreated ? 201 : 200);
    }
}
