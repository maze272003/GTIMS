<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserLevel;
use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function index()
    {
        $roles = UserLevel::with('permissions')->get();
        $permissions = Permission::orderBy('group')->orderBy('name')->get();
        $grouped = $permissions->groupBy('group');
        return view('admin.roles.index', compact('roles', 'permissions', 'grouped'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'permissions' => 'sometimes|array',
            'permissions.*' => 'array',
            'permissions.*.*' => 'exists:permissions,id',
        ]);

        $permissionsData = $validated['permissions'] ?? [];

        foreach (UserLevel::all() as $role) {
            $rolePerms = $permissionsData[$role->id] ?? [];
            $role->permissions()->sync($rolePerms);
        }

        return back()->with('success', 'Permissions updated.');
    }
}
