<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RolePermissionManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function __construct(
        protected RolePermissionManagementService $rolePermissionManagementService
    ) {
    }

    public function index(Request $request)
    {
        $data = $this->rolePermissionManagementService->getIndexData(
            $request->integer('user') ?: null,
            $request->string('search')->toString() ?: null
        );

        if ($request->expectsJson() || $request->ajax()) {
            return $this->fragmentResponse($data);
        }

        return view('admin.roles.index', $data);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'search' => 'nullable|string',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $user = $this->rolePermissionManagementService->updatePermissions(
            (int) $validated['user_id'],
            $validated['permissions'] ?? []
        );

        $search = $validated['search'] ?? null;
        $message = 'Permissions updated for '.$user->name.'.';

        if ($request->expectsJson() || $request->ajax()) {
            return $this->fragmentResponse(
                $this->rolePermissionManagementService->getIndexData($user->id, $search),
                $message
            );
        }

        return redirect()
            ->route('admin.roles.index', array_filter([
                'user' => $user->id,
                'search' => $search,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', $message);
    }

    protected function fragmentResponse(array $data, ?string $message = null): JsonResponse
    {
        return response()->json([
            'header_actions_html' => view('admin.roles.partials.header-actions', $data)->render(),
            'directory_html' => view('admin.roles.partials.directory', $data)->render(),
            'workspace_html' => view('admin.roles.partials.workspace', $data)->render(),
            'selected_user_id' => $data['selectedUser']?->id,
            'search' => $data['search'] ?? '',
            'message' => $message,
            'url' => route('admin.roles.index', array_filter([
                'user' => $data['selectedUser']?->id,
                'search' => $data['search'] ?? null,
            ], fn ($value) => $value !== null && $value !== '')),
        ]);
    }
}
