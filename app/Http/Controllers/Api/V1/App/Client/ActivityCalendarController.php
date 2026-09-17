<?php

namespace App\Http\Controllers\Api\V1\App\Client;

use App\Http\Controllers\Controller;
use App\Services\AthleteActivityCalendarService;
use Illuminate\Http\Request;

class ActivityCalendarController extends Controller
{
    public function index(Request $request, AthleteActivityCalendarService $calendar)
    {
        $data = $request->validate([
            'month' => ['nullable', 'string', 'date_format:Y-m'],
        ]);

        $clientId = $request->user()->client_id ?? null;

        if (!$clientId) {
            return response()->json([
                'ok' => false,
                'message' => 'Cliente no identificado.',
            ], 422);
        }

        return response()->json(
            $calendar->month((int) $clientId, $data['month'] ?? null)
        );
    }
}
