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

        if (!(bool)$section->accepts_results) {
            return response()->json(['ok' => false, 'message' => 'Esta sección no acepta resultados.'], 422);
        }

        $data = $request->validate([
            'training_assignment_id' => ['required', 'integer', 'exists:training_assignments,id'],
            'value' => ['required'],
            'unit' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        /** @var TrainingAssignment $assignment */
        $assignment = TrainingAssignment::query()->findOrFail($data['training_assignment_id']);

        // Ownership
        if ((int)$assignment->client_id !== (int)$clientId) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        // Asegurar que la sección pertenece al mismo training_session del assignment
        if ((int)$section->training_session_id !== (int)$assignment->training_session_id) {
            return response()->json(['ok' => false, 'message' => 'Sección no corresponde al entrenamiento asignado.'], 422);
        }

        // $type = $section->result_type;

        // if (!in_array($type, ['number', 'time', 'text', 'bool', 'json'], true)) {
        //     return response()->json(['ok' => false, 'message' => 'Tipo de resultado inválido.'], 422);
        // }
        $type = 'number';

        // Unidad default desde la sección si no viene en request.
        $defaultUnit = $section->result_type; // ej. 'kg' o null

        $payload = [
            'training_assignment_id' => $assignment->id,
            'training_section_id' => $section->id,
            'client_id' => $clientId,
            'result_type' => $type,

            'value_number' => null,
            'value_time_seconds' => null,
            'value_text' => null,
            'value_bool' => null,
            'value_json' => null,

            // 'unit' => $data['unit'] ?? null,
            'unit' => $data['unit'] ?? $defaultUnit,
            'notes' => $data['notes'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? null,
        ];

        $value = $data['value'];

        switch ($type) {
            case 'number':
                if (!is_numeric($value)) {
                    return response()->json(['ok' => false, 'message' => 'value debe ser numérico.'], 422);
                }
                $payload['value_number'] = (float)$value;
                break;

            case 'time':
                // MVP: segundos (int)
                if (!is_numeric($value) || (int)$value < 0) {
                    return response()->json(['ok' => false, 'message' => 'value debe ser segundos (entero >= 0).'], 422);
                }
                $payload['value_time_seconds'] = (int)$value;
                break;

            case 'text':
                if (!is_string($value)) {
                    return response()->json(['ok' => false, 'message' => 'value debe ser texto.'], 422);
                }
                $payload['value_text'] = $value;
                break;

            case 'bool':
                if (is_bool($value)) {
                    $payload['value_bool'] = $value;
                } elseif (is_numeric($value)) {
                    $payload['value_bool'] = ((int)$value) === 1;
                } elseif (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
                    $payload['value_bool'] = strtolower($value) === 'true';
                } else {
                    return response()->json(['ok' => false, 'message' => 'value debe ser boolean.'], 422);
                }
                break;

            case 'json':
                if (!is_array($value)) {
                    return response()->json(['ok' => false, 'message' => 'value debe ser un objeto/array JSON.'], 422);
                }
                $payload['value_json'] = $value;
                break;
        }

        $result = TrainingSectionResult::create($payload);

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $result->id,
                'training_assignment_id' => $result->training_assignment_id,
                'training_section_id' => $result->training_section_id,
                'result_type' => $result->result_type,
                'value' => $result->normalizedValue(),
                'unit' => $result->unit,
                'notes' => $result->notes,
                'created_at' => optional($result->created_at)->toISOString(),
            ],
        ], 201);
    }
}
