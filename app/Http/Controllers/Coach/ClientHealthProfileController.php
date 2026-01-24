<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientHealthProfile;
use Illuminate\Http\Request;

class ClientHealthProfileController extends Controller
{
    public function update(Request $request, Client $client)
    {
        abort_unless($client->coach_id === auth()->id(), 403);

        $data = $request->validate([
            'state'      => ['nullable','string','max:100'],
            'city'       => ['nullable','string','max:120'],
            'zip_code'   => ['nullable','string','max:20'],
            'birth_date' => ['nullable','date'],
            'gender'     => ['nullable','string','max:30'],
            'height_cm'  => ['nullable','integer','min:50','max:260'],
        ]);

        // upsert 1:1
        ClientHealthProfile::updateOrCreate(
            ['client_id' => $client->id],
            $data
        );

        return back()->with('success', 'Perfil de salud actualizado.');
    }
}
