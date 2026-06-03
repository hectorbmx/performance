<?php

namespace App\Http\Controllers\Api\V1\App\Client;

use App\Http\Controllers\Controller;
use App\Services\AppStreakService;
use Illuminate\Http\Request;

class StreakController extends Controller
{
    public function show(Request $request, AppStreakService $streaks)
    {
        $clientId = $request->user()->client_id ?? null;

        if (!$clientId) {
            return response()->json([
                'ok' => false,
                'message' => 'Cliente no identificado.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => $streaks->summaryForClient((int) $clientId),
        ]);
    }
}
