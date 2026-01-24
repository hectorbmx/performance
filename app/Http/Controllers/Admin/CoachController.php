<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CoachController extends Controller
{
    public function index()
    {
       $coaches = \App\Models\User::role('coach')
            ->with([
                'coachProfile',
                'latestSubscription',
                'subscriptions' => function ($q) {
                    $q->whereNull('deleted_at')
                    ->orderByDesc('ends_at')
                    ->limit(1);
                }
            ])
            ->orderBy('name')
            ->paginate(15);

        return view('admin.coaches.index', compact('coaches'));
    }

    public function create()
    {
        return view('admin.coaches.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['required','string','max:255'],
            'email'        => ['required','email','max:255','unique:users,email'],
            'password'     => ['required','string','min:8','confirmed'],
            'display_name' => ['required','string','max:255'],
            'phone'        => ['nullable','string','max:50'],
            'status'       => ['required', Rule::in(['active','inactive','trial'])],
        ]);

        $coachUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $coachUser->assignRole('coach');

        CoachProfile::create([
            'user_id' => $coachUser->id,
            'display_name' => $validated['display_name'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('admin.coaches.index')
            ->with('success', 'Coach creado correctamente.');
    }

    public function edit(User $coach)
    {
        abort_unless($coach->hasRole('coach'), 404);

        $coach->load('coachProfile');

        return view('admin.coaches.edit', compact('coach'));
    }

    public function update(Request $request, User $coach)
    {
        abort_unless($coach->hasRole('coach'), 404);

        $validated = $request->validate([
            'name'         => ['required','string','max:255'],
            'email'        => ['required','email','max:255', Rule::unique('users','email')->ignore($coach->id)],
            'display_name' => ['required','string','max:255'],
            'phone'        => ['nullable','string','max:50'],
            'status'       => ['required', Rule::in(['active','inactive','trial','suspended','cancelled'])],

            // suspensión opcional
            'suspension_reason' => ['nullable','string','max:255'],
            'suspend'           => ['nullable','boolean'],
        ]);

        $coach->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);
        
        $profile = $coach->coachProfile;

        $shouldSuspend = (bool)($request->boolean('suspend'));

        $statusToSave = $validated['status'];

        if ($shouldSuspend) {
            $statusToSave = 'suspended';
        }

        $profile->update([
            'display_name' => $validated['display_name'],
            'phone' => $validated['phone'] ?? null,
            'status' => $statusToSave,
            'suspended_at' => $shouldSuspend ? now() : null,
            'suspension_reason' => $shouldSuspend ? ($validated['suspension_reason'] ?? 'Suspensión manual') : null,
            'updated_by' => auth()->id(),
        ]);



        return redirect()->route('admin.coaches.index')
            ->with('success', 'Coach actualizado correctamente.');
    }

    public function toggleStatus(User $coach)
    {
        abort_unless($coach->hasRole('coach'), 404);

        $profile = $coach->coachProfile;

        if (! $profile) {
            abort(404);
        }

        // Alterna active <-> inactive (si está suspended/cancelled, lo deja intacto)
        if ($profile->status === 'active') {
            $profile->status = 'inactive';
        } elseif ($profile->status === 'inactive') {
            $profile->status = 'active';
        }

        $profile->updated_by = auth()->id();
        $profile->save();

        return back()->with('success', 'Estatus actualizado.');
    }
}
