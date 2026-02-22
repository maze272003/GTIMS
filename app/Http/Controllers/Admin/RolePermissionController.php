<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RolePermissionManagementService;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function __construct(
        protected RolePermissionManagementService $rolePermissionManagementService
    ) {
    }

    public function index()
    {
        return view('admin.roles.index', $this->rolePermissionManagementService->getIndexData());
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'permissions' => 'sometimes|array',
            'permissions.*' => 'array',
            'permissions.*.*' => 'exists:permissions,id',
        ]);

        $this->rolePermissionManagementService->updatePermissions($validated['permissions'] ?? []);

        return back()->with('success', 'Permissions updated.');
    }
}

