<?php

namespace App\Http\Controllers\Api\V1\App\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientDailyHealthMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HealthMetricController extends Controller
{
    public function index(Request $request)
    {
        $userApp = $request->user();
        $client = $userApp->client;
        abort_if(!$client, 422, 'Cliente no asociado.');

        $days = min(max((int) $request->query('days', 30), 1), 90);
        $from = Carbon::today()->subDays($days - 1)->toDateString();

        $items = ClientDailyHealthMetric::query()
            ->where('client_id', $client->id)
            ->whereDate('metric_date', '>=', $from)
            ->orderBy('metric_date')
            ->get()
            ->map(fn (ClientDailyHealthMetric $item) => $this->payload($item))
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $items,
        ]);
    }

    public function sync(Request $request)
    {
        $userApp = $request->user();
        $client = $userApp->client;
        abort_if(!$client, 422, 'Cliente no asociado.');

        $data = $request->validate([
            'items' => ['required', 'array', 'max:90'],
            'items.*.date' => ['required', 'date'],
            'items.*.steps' => ['nullable', 'integer', 'min:0'],
            'items.*.calories' => ['nullable', 'integer', 'min:0'],
            'items.*.active_minutes' => ['nullable', 'integer', 'min:0'],
            'items.*.source' => ['nullable', 'string', 'max:50'],
        ]);

        $saved = collect($data['items'])->map(function (array $item) use ($client) {
            return ClientDailyHealthMetric::updateOrCreate(
                [
                    'client_id' => $client->id,
                    'metric_date' => Carbon::parse($item['date'])->toDateString(),
                ],
                [
                    'steps' => (int) ($item['steps'] ?? 0),
                    'calories' => (int) ($item['calories'] ?? 0),
                    'active_minutes' => (int) ($item['active_minutes'] ?? 0),
                    'source' => $item['source'] ?? 'device',
                ]
            );
        });

        return response()->json([
            'ok' => true,
            'data' => $saved->map(fn (ClientDailyHealthMetric $item) => $this->payload($item))->values(),
        ]);
    }

    private function payload(ClientDailyHealthMetric $item): array
    {
        return [
            'date' => $item->metric_date->toDateString(),
            'steps' => $item->steps,
            'calories' => $item->calories,
            'active_minutes' => $item->active_minutes,
            'source' => $item->source,
        ];
    }
}
