<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Ticket permissions
            'view tickets',
            'create tickets',
            'edit tickets',
            'delete tickets',
            'assign tickets',
            'resolve tickets',
            'view all tickets',
            'view internal notes',

            // Message permissions
            'send messages',
            'send internal messages',

            // Category permissions
            'view categories',
            'manage categories',

            // User permissions
            'manage users',
            'view users',

            // Dashboard permissions
            'view dashboard',
            'view statistics',

            // Notification permissions
            'manage notifications',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Admin role - full access
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->givePermissionTo(Permission::all());

        // CSKH role - customer service
        $cskhRole = Role::firstOrCreate(['name' => 'CSKH']);
        $cskhRole->givePermissionTo([
            'view tickets',
            'view all tickets',
            'edit tickets',
            'assign tickets',
            'resolve tickets',
            'send messages',
            'send internal messages',
            'view internal notes',
            'view categories',
            'view dashboard',
            'view statistics',
            'manage notifications',
        ]);

        // User role - regular users
        $userRole = Role::firstOrCreate(['name' => 'User']);
        $userRole->givePermissionTo([
            'view tickets',
            'create tickets',
            'send messages',
            'view categories',
        ]);

        $this->command->info('Roles and permissions created successfully.');
        $this->command->info('Admin: Full access');
        $this->command->info('CSKH: Ticket management, messages, dashboard');
        $this->command->info('User: Create tickets, view own tickets');
    }
}
