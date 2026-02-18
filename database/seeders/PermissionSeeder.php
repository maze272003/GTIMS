<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\UserLevel;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Inventory
            ['name' => 'inventory.view', 'group' => 'Inventory', 'description' => 'View inventory'],
            ['name' => 'inventory.add', 'group' => 'Inventory', 'description' => 'Add products and stock'],
            ['name' => 'inventory.edit', 'group' => 'Inventory', 'description' => 'Edit products and stock'],
            ['name' => 'inventory.archive', 'group' => 'Inventory', 'description' => 'Archive/unarchive products'],
            ['name' => 'inventory.transfer', 'group' => 'Inventory', 'description' => 'Transfer stock between branches'],

            // Holds
            ['name' => 'holds.view', 'group' => 'Holds', 'description' => 'View holds'],
            ['name' => 'holds.create', 'group' => 'Holds', 'description' => 'Create holds'],
            ['name' => 'holds.approve', 'group' => 'Holds', 'description' => 'Approve holds'],
            ['name' => 'holds.release', 'group' => 'Holds', 'description' => 'Release holds'],

            // Requests
            ['name' => 'requests.view', 'group' => 'Requests', 'description' => 'View requests'],
            ['name' => 'requests.create', 'group' => 'Requests', 'description' => 'Create requests'],
            ['name' => 'requests.approve', 'group' => 'Requests', 'description' => 'Approve/deny requests'],
            ['name' => 'requests.fulfill', 'group' => 'Requests', 'description' => 'Fulfill requests'],

            // Suppliers
            ['name' => 'suppliers.view', 'group' => 'Suppliers', 'description' => 'View suppliers'],
            ['name' => 'suppliers.manage', 'group' => 'Suppliers', 'description' => 'Create/edit suppliers'],

            // Settings
            ['name' => 'settings.low_stock', 'group' => 'Settings', 'description' => 'Manage low stock settings'],
            ['name' => 'settings.roles', 'group' => 'Settings', 'description' => 'Manage role permissions'],

            // Reports
            ['name' => 'reports.view', 'group' => 'Reports', 'description' => 'View reports and analytics'],
            ['name' => 'reports.export', 'group' => 'Reports', 'description' => 'Export reports'],

            // Audit
            ['name' => 'audit.view', 'group' => 'Audit', 'description' => 'View audit logs'],

            // Notifications
            ['name' => 'notifications.manage', 'group' => 'Notifications', 'description' => 'Manage notification preferences'],

            // Users
            ['name' => 'users.view', 'group' => 'Users', 'description' => 'View users'],
            ['name' => 'users.manage', 'group' => 'Users', 'description' => 'Create/edit users'],

            // Patient Records
            ['name' => 'patients.view', 'group' => 'Patients', 'description' => 'View patient records'],
            ['name' => 'patients.manage', 'group' => 'Patients', 'description' => 'Add/edit patient records'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // Assign default permissions to roles
        $superadmin = UserLevel::where('name', 'superadmin')->first();
        $admin = UserLevel::where('name', 'admin')->first();
        $encoder = UserLevel::where('name', 'encoder')->first();

        $allPermissionIds = Permission::pluck('id')->toArray();

        // Superadmin gets all permissions
        if ($superadmin) {
            $superadmin->permissions()->sync($allPermissionIds);
        }

        // Admin gets most permissions except role management
        if ($admin) {
            $adminPerms = Permission::where('name', '!=', 'settings.roles')
                ->where('name', '!=', 'users.manage')
                ->pluck('id')->toArray();
            $admin->permissions()->sync($adminPerms);
        }

        // Encoder gets view-only permissions
        if ($encoder) {
            $encoderPerms = Permission::whereIn('name', [
                'inventory.view', 'inventory.add', 'inventory.edit',
                'requests.view', 'requests.create',
                'holds.view', 'patients.view',
                'notifications.manage',
            ])->pluck('id')->toArray();
            $encoder->permissions()->sync($encoderPerms);
        }
    }
}
