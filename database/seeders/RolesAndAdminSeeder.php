<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Roles base
        $adminRole  = Role::firstOrCreate(['name' => 'admin']);
        $coachRole  = Role::firstOrCreate(['name' => 'coach']);
        $clientRole = Role::firstOrCreate(['name' => 'client']);

        // Admin global
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin Global',
                'password' => bcrypt('Qwerty123.'),
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }
    }
}
