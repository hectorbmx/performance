<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        // Roles base (con guard)
        $adminRole  = Role::firstOrCreate(['name' => 'admin',  'guard_name' => $guard]);
        $coachRole  = Role::firstOrCreate(['name' => 'coach',  'guard_name' => $guard]);
        $clientRole = Role::firstOrCreate(['name' => 'client', 'guard_name' => $guard]);

        // Admin global
        $admin = User::firstOrCreate(
            ['email' => 'hectorbmx@gmail.com'],
            [
                'name' => 'Admin Global Barreto',
                'password' => Hash::make('Qwerty123.'),
            ]
        );

        // (Opcional) si ya existía y quieres asegurar password en seed:
        // $admin->update(['password' => Hash::make('Qwerty123.')]);

        // Asignar rol
        if (! $admin->hasRole($adminRole)) {
            $admin->assignRole($adminRole);
        }
    }
}
