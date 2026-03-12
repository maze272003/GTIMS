<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRoutePermission
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $routePermissionMap = [
        'admin.roles.*' => ['settings.roles'],
        'admin.branches.*' => ['branches.manage'],
        'admin.manageaccount' => ['users.manage'],
        'admin.manageaccount.store' => ['users.manage'],
        'admin.manageaccount.update' => ['users.manage'],

        'admin.workflows.store' => ['workflows.create'],
        'admin.workflows.save-graph' => ['workflows.edit'],
        'admin.workflows.validate' => ['workflows.edit'],
        'admin.workflows.publish' => ['workflows.publish'],
        'admin.workflows.disable' => ['workflows.edit'],
        'admin.workflows.run' => ['workflows.run'],
        'admin.workflows.destroy' => ['workflows.delete'],
        'admin.workflows.permissions.add' => ['workflows.edit'],
        'admin.workflows.permissions.remove' => ['workflows.edit'],
        'admin.workflows.versions.rollback' => ['workflows.publish'],
        'admin.workflows.runs.rerun' => ['workflows.run'],
        'admin.workflows.*' => ['workflows.view'],

        'admin.lowstock.*' => ['settings.low_stock'],
        'admin.notifications.*' => ['notifications.manage'],
        'admin.analytics.*' => ['reports.view'],
        'admin.audit.*' => ['audit.view'],
        'admin.suppliers.create' => ['suppliers.manage'],
        'admin.suppliers.store' => ['suppliers.manage'],
        'admin.suppliers.edit' => ['suppliers.manage'],
        'admin.suppliers.update' => ['suppliers.manage'],
        'admin.suppliers.link-inventory' => ['suppliers.manage'],
        'admin.suppliers.unlink-inventory' => ['suppliers.manage'],
        'admin.suppliers.*' => ['suppliers.view'],

        'admin.requests.create' => ['requests.create'],
        'admin.requests.store' => ['requests.create'],
        'admin.requests.transition' => ['requests.approve'],
        'admin.requests.fulfill' => ['requests.fulfill'],
        'admin.requests.comment' => ['requests.view'],
        'admin.requests.attachment' => ['requests.view'],
        'admin.requests.*' => ['requests.view'],

        'admin.holds.create' => ['holds.create'],
        'admin.holds.store' => ['holds.create'],
        'admin.holds.approve' => ['holds.approve'],
        'admin.holds.release' => ['holds.release'],
        'admin.holds.cancel' => ['holds.approve', 'holds.release'],
        'admin.holds.pullout' => ['holds.release'],
        'admin.holds.*' => ['holds.view'],

        'admin.historylog' => ['historylog.view'],
        'admin.movements' => ['movements.view'],
        'admin.ai.analysis' => ['dashboard.view'],
        'admin.inventory.addproduct' => ['inventory.add'],
        'admin.inventory.addstock' => ['inventory.add'],
        'admin.inventory.updateproduct' => ['inventory.edit'],
        'admin.inventory.editstock' => ['inventory.edit'],
        'admin.inventory.archiveproduct' => ['inventory.archive'],
        'admin.inventory.unarchiveproduct' => ['inventory.archive'],
        'admin.inventory.fetchArchivedStocks' => ['inventory.archive'],
        'admin.inventory.transferstock' => ['inventory.transfer'],
        'admin.inventory.export' => ['inventory.view', 'reports.export'],
        'admin.inventory' => ['inventory.view'],

        'admin.patientrecords.adddispensation' => ['patients.manage'],
        'admin.patientrecords.update' => ['patients.manage'],
        'admin.patientrecords.exportPdf' => ['patients.view', 'reports.export'],
        'admin.patientrecords.exportExcel' => ['patients.view', 'reports.export'],
        'admin.patientrecords' => ['patients.view'],

        'admin.orders.create' => ['orders.create'],
        'admin.orders.store' => ['orders.create'],
        'admin.orders.source-inventory' => ['orders.create'],
        'admin.orders.update' => ['orders.approve_admin', 'orders.approve_finance'],
        'admin.orders.print' => ['orders.view'],
        'admin.orders.*' => ['orders.view'],

        'admin.dashboard' => ['dashboard.view'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (!$routeName || !str_starts_with($routeName, 'admin.')) {
            return $next($request);
        }

        $requiredPermissions = $this->resolvePermissionsForRoute($routeName);

        if ($requiredPermissions === []) {
            return $next($request);
        }

        $user = $request->user();

        foreach ($requiredPermissions as $permission) {
            if ($user && $user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'This page or action cannot be accessed with your account. Please contact the superadmin for assistance.');
    }

    /**
     * @return array<int, string>
     */
    protected function resolvePermissionsForRoute(string $routeName): array
    {
        foreach ($this->routePermissionMap as $pattern => $permissions) {
            if (Str::is($pattern, $routeName)) {
                return $permissions;
            }
        }

        return [];
    }
}
