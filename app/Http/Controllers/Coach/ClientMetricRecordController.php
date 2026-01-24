<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientMetricRecord;
use App\Models\TrainingMetric;
use Illuminate\Http\Request;

class ClientMetricRecordController extends Controller
{
    public function store(Request $request, Client $client)
    {
        abort_unless($client->coach_id === auth()->id(), 403);

        $data = $request->validate([
            'training_metric_id' => ['required','integer'],
            'value'              => ['required','numeric','min:0'],
            'recorded_at'        => ['required','date'], // viene como YYYY-MM-DD desde input date
            'source'             => ['nullable','string','max:30'],
            'notes'              => ['nullable','string','max:255'],
        ]);

        // Validar que la métrica pertenezca al coach
        $metric = TrainingMetric::where('id', $data['training_metric_id'])
            ->where('coach_id', auth()->id())
            ->where('is_active', 1)
            ->firstOrFail();

        // Guardar (recorded_at lo guardamos como timestamp con hora actual)
        $client->metricRecords()->create([
            'training_metric_id' => $metric->id,
            'value'              => $data['value'],
            'recorded_at'        => $data['recorded_at'] . ' ' . now()->format('H:i:s'),
            'source'             => $data['source'] ?? 'manual',
            'notes'              => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Métrica registrada.');
    }

    public function destroy(Client $client, ClientMetricRecord $record)
    {
        abort_unless($client->coach_id === auth()->id(), 403);
        abort_unless((int)$record->client_id === (int)$client->id, 404);

        $record->delete();

        return back()->with('success', 'Métrica eliminada.');
    }
}
