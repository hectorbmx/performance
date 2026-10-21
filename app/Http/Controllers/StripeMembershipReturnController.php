<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StripeMembershipReturnController extends Controller
{
    public function __invoke(Request $request)
    {
        $result = $request->query('stripe') === 'success' ? 'success' : 'cancel';
        $sessionId = (string) $request->query('session_id', '');

        $query = ['stripe' => $result];
        if ($result === 'success' && preg_match('/^[A-Za-z0-9_\\-]+$/', $sessionId)) {
            $query['session_id'] = $sessionId;
        }

        return redirect()->away(
            'com.app33performance://subscription-history?'.http_build_query($query)
        );
    }
}
