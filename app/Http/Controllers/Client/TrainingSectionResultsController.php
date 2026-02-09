<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\TrainingAssignment;
use App\Models\TrainingSection;
use App\Models\TrainingSectionResult;
use Illuminate\Http\Request;

class TrainingSectionResultsController extends Controller
{
    public function store(Request $request, TrainingSection $section)
{
    $user = $request->user();
    $clientId = $user->client_id ?? null;

    if (!$clientId) {
        return response()->json(['ok' => false, 'message' => 'Cliente no identificado.'], 422);
    }

    if (!(bool) $section->accepts_results) {
        return response()->json(['ok' => false, 'message' => 'Esta sección no acepta resultados.'], 422);
    }

    $data = $request->validate([
        'training_assignment_id' => ['required', 'integer', 'exists:training_assignments,id'],
        'value' => ['nullable'], // ⚠️ depende del tipo, lo validamos abajo
        'unit' => ['nullable', 'string', 'max:20'],
        'notes' => ['nullable', 'string', 'max:2000'],
        'recorded_at' => ['nullable', 'date'],
    ]);

    /** @var TrainingAssignment $assignment */
    $assignment = TrainingAssignment::query()->findOrFail($data['training_assignment_id']);

    if ((int) $assignment->client_id !== (int) $clientId) {
        return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
    }

    if ((int) $section->training_session_id !== (int) $assignment->training_session_id) {
        return response()->json(['ok' => false, 'message' => 'Sección no corresponde al entrenamiento asignado.'], 422);
    }

    // Tipo real desde la sección
    $type = (string) ($section->result_type ?? 'none');

    $allowed = ['none','reps','time','weight','distance','rounds','sets','calories','points','note','boolean'];
    if (!in_array($type, $allowed, true)) {
        return response()->json(['ok' => false, 'message' => 'Tipo de resultado inválido en la sección.'], 422);
    }

    // Base payload
    $payload = [
        'training_assignment_id' => $assignment->id,
        'training_section_id'    => $section->id,
        'client_id'              => $clientId,
        'result_type'            => $type,

        'value_number'       => null,
        'value_time_seconds' => null,
        'value_text'         => null,
        'value_bool'         => null,
        'value_json'         => null,

        'unit'        => $data['unit'] ?? null,
        'notes'       => $data['notes'] ?? null,
        'recorded_at' => $data['recorded_at'] ?? now(),
    ];

    $value = $data['value'] ?? null;

    // Helper: exigir value
    $requireValue = function () use ($value) {
        return !is_null($value) && !(is_string($value) && trim($value) === '');
    };

    switch ($type) {
        case 'none':
            // No requiere value. Guardamos solo notas/recorded_at si quieres.
            break;

        case 'note':
            if (!$requireValue() || !is_string($value)) {
                return response()->json(['ok' => false, 'message' => 'value debe ser texto.'], 422);
            }
            $payload['value_text'] = $value;
            break;

        case 'boolean':
            if (is_bool($value)) {
                $payload['value_bool'] = $value ? 1 : 0;
            } elseif (is_numeric($value)) {
                $payload['value_bool'] = ((int) $value) === 1 ? 1 : 0;
            } elseif (is_string($value) && in_array(strtolower($value), ['true','false','1','0'], true)) {
                $payload['value_bool'] = in_array(strtolower($value), ['true','1'], true) ? 1 : 0;
            } else {
                return response()->json(['ok' => false, 'message' => 'value debe ser boolean.'], 422);
            }
            break;

        case 'time':
            // Acepta: segundos numéricos OR "mm:ss" OR "hh:mm:ss"
            if (!$requireValue()) {
                return response()->json(['ok' => false, 'message' => 'value es requerido para tiempo.'], 422);
            }

            $seconds = null;

            if (is_numeric($value)) {
                $seconds = (int) $value;
            } elseif (is_string($value)) {
                $v = trim($value);
                if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $v)) {
                    $parts = array_map('intval', explode(':', $v));
                    if (count($parts) === 2) { // mm:ss
                        [$m,$s] = $parts;
                        $seconds = ($m * 60) + $s;
                    } else { // hh:mm:ss
                        [$h,$m,$s] = $parts;
                        $seconds = ($h * 3600) + ($m * 60) + $s;
                    }
                }
            }

            if ($seconds === null || $seconds < 0) {
                return response()->json(['ok' => false, 'message' => 'value debe ser segundos o formato mm:ss / hh:mm:ss.'], 422);
            }

            $payload['value_time_seconds'] = $seconds;
            break;

        // Enteros
        case 'reps':
        case 'rounds':
        case 'sets':
            if (!$requireValue() || !is_numeric($value) || (int)$value < 0) {
                return response()->json(['ok' => false, 'message' => 'value debe ser entero >= 0.'], 422);
            }
            $payload['value_number'] = (int) $value;
            break;

        // Numéricos (float)
        case 'weight':
        case 'distance':
        case 'calories':
        case 'points':
            if (!$requireValue() || !is_numeric($value)) {
                return response()->json(['ok' => false, 'message' => 'value debe ser numérico.'], 422);
            }
            $payload['value_number'] = (float) $value;
            break;
    }

    // Upsert por (training_assignment_id, training_section_id)
    $result = TrainingSectionResult::query()->updateOrCreate(
        [
            'training_assignment_id' => $assignment->id,
            'training_section_id'    => $section->id,
        ],
        $payload
    );

    if ($assignment->status === 'scheduled') {
        $assignment->update(['status' => 'in_progress']);
    }

    return response()->json([
        'ok' => true,
        'data' => [
            'id' => $result->id,
            'training_assignment_id' => $result->training_assignment_id,
            'training_section_id' => $result->training_section_id,
            'result_type' => $result->result_type,
            'value' => method_exists($result, 'normalizedValue') ? $result->normalizedValue() : (
                $result->value_number ?? $result->value_time_seconds ?? $result->value_text ?? $result->value_bool ?? $result->value_json
            ),
            'unit' => $section->unit?->symbol,
            'notes' => $result->notes,
            'recorded_at' => optional($result->recorded_at)->toISOString(),
            'updated_at' => optional($result->updated_at)->toISOString(),
        ],
    ], 200);
}
public function update(Request $request, TrainingSection $section)
{
    return $this->store($request, $section);
}

}
