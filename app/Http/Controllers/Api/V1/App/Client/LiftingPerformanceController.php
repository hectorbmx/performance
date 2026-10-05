<?php

namespace App\Http\Controllers\Api\V1\App\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientLiftingPerformanceService;
use Illuminate\Http\Request;

class LiftingPerformanceController extends Controller
{
    public function show(Request $request, ClientLiftingPerformanceService $performance)
    {
        $clientId = $request->user()->client_id ?? null;

        if (!$clientId) {
            return response()->json([
                'ok' => false,
                'message' => 'Cliente no identificado.',
            ], 422);
        }

        $client = Client::query()->find($clientId);

        if (!$client) {
            return response()->json([
                'ok' => false,
                'message' => 'Cliente no encontrado.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $performance->forClient($client),
        ]);
    }
}
