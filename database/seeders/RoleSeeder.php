<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Tạo 3 vai trò cố định
        $roles = [
            ['name' => Role::ADMIN,    'label' => 'Quản trị viên'],
            ['name' => Role::STAFF,    'label' => 'Nhân viên'],
            ['name' => Role::CUSTOMER, 'label' => 'Khách hàng'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }

        // Tạo tài khoản admin đầu tiên để test đăng nhập
        $adminRole = Role::where('name', Role::ADMIN)->first();

        User::firstOrCreate(
            ['email' => 'admin@psvtravel.com'],
            [
                'role_id'           => $adminRole->id,
                'name'              => 'Quản trị viên PSVTravel',
                'password'          => Hash::make('Admin@123456'),
                'email_verified_at' => now(),
                'locale'            => 'vi',
            ]
        );
    }
}