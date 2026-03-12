<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'dashboard.view', 'group' => 'Dashboard', 'description' => 'View dashboard'],

            // Orders
            ['name' => 'orders.view', 'group' => 'Orders', 'description' => 'View orders'],
            ['name' => 'orders.create', 'group' => 'Orders', 'description' => 'Create orders'],
            ['name' => 'orders.approve_admin', 'group' => 'Orders', 'description' => 'Approve orders as admin'],
            ['name' => 'orders.approve_finance', 'group' => 'Orders', 'description' => 'Approve orders as finance'],

            // Inventory
            ['name' => 'inventory.view', 'group' => 'Inventory', 'description' => 'View inventory'],
            ['name' => 'inventory.add', 'group' => 'Inventory', 'description' => 'Add products and stock'],
            ['name' => 'inventory.edit', 'group' => 'Inventory', 'description' => 'Edit products and stock'],
            ['name' => 'inventory.archive', 'group' => 'Inventory', 'description' => 'Archive/unarchive products'],
            ['name' => 'inventory.transfer', 'group' => 'Inventory', 'description' => 'Transfer stock between branches'],

            // Product Movements
            ['name' => 'movements.view', 'group' => 'Movements', 'description' => 'View product movements'],

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
            ['name' => 'branches.manage', 'group' => 'Settings', 'description' => 'Manage branch lifecycle and archival'],

            // Reports
            ['name' => 'reports.view', 'group' => 'Reports', 'description' => 'View reports and analytics'],
            ['name' => 'reports.export', 'group' => 'Reports', 'description' => 'Export reports'],

            // Audit
            ['name' => 'audit.view', 'group' => 'Audit', 'description' => 'View audit logs'],

            // History Logs
            ['name' => 'historylog.view', 'group' => 'History', 'description' => 'View history logs'],

            // Notifications
            ['name' => 'notifications.manage', 'group' => 'Notifications', 'description' => 'Manage notification preferences'],

            // Workflows / Automation Builder
            ['name' => 'workflows.view', 'group' => 'Workflows', 'description' => 'View automation workflows'],
            ['name' => 'workflows.create', 'group' => 'Workflows', 'description' => 'Create automation workflows'],
            ['name' => 'workflows.edit', 'group' => 'Workflows', 'description' => 'Edit automation workflows'],
            ['name' => 'workflows.publish', 'group' => 'Workflows', 'description' => 'Publish automation workflows'],
            ['name' => 'workflows.run', 'group' => 'Workflows', 'description' => 'Run automation workflows'],
            ['name' => 'workflows.delete', 'group' => 'Workflows', 'description' => 'Delete automation workflows'],

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
        $doctor = UserLevel::where('name', 'doctor')->first();
        $mayor = UserLevel::where('name', 'mayor')->first();
        $finance = UserLevel::where('name', 'finance')->first();

        $allPermissionIds = Permission::pluck('id')->toArray();

        // Superadmin gets all permissions
        if ($superadmin) {
            $superadmin->permissions()->sync($allPermissionIds);
        }

        // Admin gets most permissions except role management and user management
        if ($admin) {
            $adminPerms = Permission::where('name', '!=', 'settings.roles')
                ->where('name', '!=', 'branches.manage')
                ->where('name', '!=', 'users.manage')
                ->pluck('id')->toArray();
            $admin->permissions()->sync($adminPerms);
        }

        // Encoder gets view-only and create permissions
        if ($encoder) {
            $encoderPerms = Permission::whereIn('name', [
                'dashboard.view',
                'inventory.view', 'inventory.add', 'inventory.edit',
                'requests.view', 'requests.create',
                'holds.view', 'patients.view',
                'notifications.manage',
            ])->pluck('id')->toArray();
            $encoder->permissions()->sync($encoderPerms);
        }

        // Doctor gets dashboard and patient records access
        if ($doctor) {
            $doctorPerms = Permission::whereIn('name', [
                'dashboard.view',
                'inventory.view',
                'patients.view',
            ])->pluck('id')->toArray();
            $doctor->permissions()->sync($doctorPerms);
        }

        // Mayor gets dashboard view
        if ($mayor) {
            $mayorPerms = Permission::whereIn('name', [
                'dashboard.view',
            ])->pluck('id')->toArray();
            $mayor->permissions()->sync($mayorPerms);
        }

        // Finance gets order-related permissions
        if ($finance) {
            $financePerms = Permission::whereIn('name', [
                'orders.view',
                'orders.approve_finance',
            ])->pluck('id')->toArray();
            $finance->permissions()->sync($financePerms);
        }

        User::query()
            ->with('level.permissions')
            ->get()
            ->each(function (User $user) {
                $permissionIds = $user->level?->permissions?->pluck('id')->all() ?? [];
                $user->permissions()->sync($permissionIds);
                $user->forceFill(['uses_custom_permissions' => true])->save();
            });
    }
}
