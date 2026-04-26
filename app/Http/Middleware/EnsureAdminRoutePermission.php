<?php

namespace App\Http\Middleware;

use App\Services\AuthSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRoutePermission
{
    /**
     * @var array<string, array<int|string, mixed>>
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
        'admin.inventory.export' => ['all' => ['inventory.view', 'reports.export']],
        'admin.inventory' => ['inventory.view'],

        'admin.patientrecords.adddispensation' => ['patients.manage'],
        'admin.patientrecords.update' => ['patients.manage'],
        'admin.patientrecords.exportPdf' => ['all' => ['patients.view', 'reports.export']],
        'admin.patientrecords.exportExcel' => ['all' => ['patients.view', 'reports.export']],
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

        $permissionRule = $this->resolvePermissionsForRoute($routeName);

        if ($permissionRule['permissions'] === []) {
            return $next($request);
        }

        $user = $request->user();

        $hasRequiredAccess = match ($permissionRule['mode']) {
            'all' => collect($permissionRule['permissions'])->every(
                fn ($permission) => $user && $user->hasPermission($permission)
            ),
            default => collect($permissionRule['permissions'])->contains(
                fn ($permission) => $user && $user->hasPermission($permission)
            ),
        };

        if ($hasRequiredAccess) {
            return $next($request);
        }

        abort(403, app(AuthSessionService::class)->getForbiddenMessage($user));
    }

    /**
     * @return array{mode:string,permissions:array<int,string>}
     */
    protected function resolvePermissionsForRoute(string $routeName): array
    {
        foreach ($this->routePermissionMap as $pattern => $permissionRule) {
            if (!Str::is($pattern, $routeName)) {
                continue;
            }

            if (is_array($permissionRule) && array_key_exists('all', $permissionRule)) {
                return [
                    'mode' => 'all',
                    'permissions' => array_values($permissionRule['all']),
                ];
            }

            return [
                'mode' => 'any',
                'permissions' => array_values($permissionRule),
            ];
        }

        return [
            'mode' => 'any',
            'permissions' => [],
        ];
    }
}
