<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@crm.com'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('admin123'),
                'phone' => '0901234567',
            ]
        );
        $admin->assignRole('Admin');

        // Create CSKH user
        $cskh = User::firstOrCreate(
            ['email' => 'cskh@crm.com'],
            [
                'name' => 'CSKH Staff',
                'password' => bcrypt('cskh123'),
                'phone' => '0901234568',
            ]
        );
        $cskh->assignRole('CSKH');

        // Create demo regular user
        $user = User::firstOrCreate(
            ['email' => 'user@crm.com'],
            [
                'name' => 'Demo User',
                'password' => bcrypt('user123'),
                'phone' => '0901234569',
            ]
        );
        $user->assignRole('User');

        $this->command->info('Default users created:');
        $this->command->info('Admin: admin@crm.com / admin123');
        $this->command->info('CSKH: cskh@crm.com / cskh123');
        $this->command->info('User: user@crm.com / user123');
    }
}
