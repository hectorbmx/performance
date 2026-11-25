<?php
namespace App\Services;
use App\Models\Client;
use App\Models\UserApp;
use App\Models\WorkoutExercise;

class WorkoutPrescription
{
    public function propose(Client|UserApp $client, WorkoutExercise $exercise, array $parameters): array {
        $rules = config('workout_prescription'); $maxes = app(WorkoutMaxes::class);
        $plan = ['sets' => $parameters['sets'] ?? $rules['sets'], 'reps_min' => $parameters['reps_min'] ?? $rules['reps_min'],
            'reps_max' => $parameters['reps_max'] ?? $rules['reps_max'], 'target_rir' => $parameters['target_rir'] ?? $rules['target_rir'],
            'rest_seconds' => $parameters['rest_seconds'] ?? $rules['rest_seconds']];
        $response = ['rule_version' => $rules['version'], 'exercise_id' => $exercise->id, 'prescribed' => $plan,
            'suggested_load' => null, 'adjustment' => 'none', 'basis' => null, 'reason' => 'Sin información suficiente para sugerir carga.', 'editable' => true];
        if (($parameters['pain_reported'] ?? false) || ($parameters['technique_issue'] ?? false)) {
            $response['reason'] = 'No aumentes la carga ante dolor o dificultad técnica. No se genera una sugerencia automática; puedes buscar orientación profesional.'; return $response;
        }
        if ($exercise->load_mode !== 'total_barbell' || $exercise->review_status !== 'reviewed') {
            $response['reason'] = 'No hay una modalidad de barra y clasificación revisada compatible para sugerir peso.'; return $response;
        }
        $basis = $maxes->basis($client, $exercise); $response['basis'] = $basis;
        if (!$basis) return $response;
        $unit = $parameters['unit'] ?? 'kg'; $step = $parameters['increment'] ?? '2.50';
        $stepMicros = $maxes->micros($maxes->kg($step, $unit)); $rmMicros = $maxes->micros($basis['value_kg']);
        $effectiveTenths = $plan['reps_min'] * 10 + (int) round($plan['target_rir'] * 10);
        if ($effectiveTenths > $rules['initial_max_effective_reps'] * 10) {
            $response['reason'] = 'El rango elegido excede el alcance configurado del modelo de carga.'; return $response;
        }
        $steps = intdiv($rmMicros * 300, (300 + $effectiveTenths) * $stepMicros);
        $adjustment = 'initial';
        $history = $maxes->history($client, $exercise); $last = $history[0] ?? null;
        if ($last && $last->load_kg !== null && $maxes->micros($last->load_kg) > 0 && $last->status !== 'skipped') {
            $lastMicros = $maxes->micros($last->load_kg);
            if ($last->status === 'failed' || $last->actual_reps < $plan['reps_min'] || ($last->actual_rir !== null && (float) $last->actual_rir < $plan['target_rir'])) {
                $steps = intdiv($lastMicros * (100 - $rules['reduction_pct']), 100 * $stepMicros); $adjustment = 'reduce';
            } else {
                $steps = intdiv($lastMicros, $stepMicros); $adjustment = 'maintain';
                $successful = array_slice($history, 0, $rules['increase_requires_sets']);
                $canIncrease = count($successful) === $rules['increase_requires_sets'];
                foreach ($successful as $set) if ($set->status !== 'completed' || $set->type !== 'work' || $set->load_kg === null
                    || abs($maxes->micros($set->load_kg ?? '0') - $lastMicros) > $stepMicros
                    || $set->actual_reps < $plan['reps_max'] || $set->actual_rir === null || (float) $set->actual_rir < $plan['target_rir'] + 1) $canIncrease = false;
                if ($canIncrease && ($steps + 1) * $stepMicros <= $rmMicros) { $steps++; $adjustment = 'increase'; }
            }
        }
        if ($steps < 1) { $response['reason'] = 'No hay una carga positiva compatible con el incremento elegido.'; return $response; }
        $originalCents = (int) round((float) $step * 100) * $steps;
        $originalValue = intdiv($originalCents, 100).'.'.str_pad((string) ($originalCents % 100), 2, '0', STR_PAD_LEFT);
        $response['suggested_load'] = ['value' => $originalValue,
            'unit' => $unit, 'value_kg' => $maxes->kg($originalValue, $unit), 'increment' => $step];
        $response['adjustment'] = $adjustment;
        $response['reason'] = match ($adjustment) {
            'reduce' => 'El desempeño reciente quedó por debajo del rango o esfuerzo objetivo; se propone reducir y revisar.',
            'maintain' => 'Se propone mantener según el registro reciente, sin evidencia suficiente para subir.',
            'increase' => 'Dos series recientes alcanzaron el rango con margen reportado; se propone un solo incremento disponible.',
            default => 'Estimación inicial desde RM e inversión del modelo Epley con RIR objetivo; puede diferir de tu desempeño real.',
        };
        return $response;
    }
}
