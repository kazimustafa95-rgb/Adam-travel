<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Adam Travel Super Admin',
                'password' => Hash::make('password'),
                'role' => AdminRole::SuperAdmin,
            ],
        );
    }
}
