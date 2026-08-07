<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'admin', 'staff', 'customer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@psvtravel.com'],
            [
                'name' => 'Quản trị viên PSVTravel',
                'password' => Hash::make('Admin@123456'),
                'email_verified_at' => now(),
                'locale' => 'vi',
            ]
        );

        $admin->syncRoles(['super_admin']);
    }
}