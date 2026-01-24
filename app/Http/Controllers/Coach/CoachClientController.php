<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class CoachClientController extends Controller
{
    public function index()
    {
        $clients = Client::where('coach_id', auth()->id())
            ->with('activeMembership')
            ->latest()
            ->paginate(10);

        return view('coach.clients.index', compact('clients'));
    }

    public function create()
    {
        return view('coach.clients.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['coach_id'] = auth()->id();
        $validated['is_active'] = $request->boolean('is_active', true);

        Client::create($validated);

        return redirect()->route('coach.clients.index')
            ->with('success', 'Cliente creado exitosamente.');
    }

    public function edit(Client $client)
    {
        // Verificar que el cliente pertenezca al coach
        if ($client->coach_id !== auth()->id()) {
            abort(403, 'No tienes permiso para editar este cliente.');
        }

        return view('coach.clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        // Verificar que el cliente pertenezca al coach
        if ($client->coach_id !== auth()->id()) {
            abort(403, 'No tienes permiso para actualizar este cliente.');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $client->update($validated);

        return redirect()->route('coach.clients.index')
            ->with('success', 'Cliente actualizado exitosamente.');
    }

    public function destroy(Client $client)
    {
        // Verificar que el cliente pertenezca al coach
        if ($client->coach_id !== auth()->id()) {
            abort(403, 'No tienes permiso para eliminar este cliente.');
        }

        $client->delete();

        return redirect()->route('coach.clients.index')
            ->with('success', 'Cliente eliminado exitosamente.');
    }
    
}