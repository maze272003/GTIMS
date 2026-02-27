<?php

namespace App\Http\Middleware;

use App\Services\SystemActivityNotificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordSystemActivityNotification
{
    private const SKIP_ROUTE_NAMES = [
        'login',
        'logout',
        'otp.send',
        'otp.verify',
        'admin.notifications.read',
        'admin.notifications.read-all',
    ];

    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly SystemActivityNotificationService $notificationService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->recordIfNeeded($request, $response);

        return $response;
    }

    private function recordIfNeeded(Request $request, Response $response): void
    {
        $user = $request->user();
        if (!$user) {
            return;
        }

        $route = $request->route();
        $routeName = $route?->getName();

        if (!$routeName || in_array($routeName, self::SKIP_ROUTE_NAMES, true)) {
            return;
        }

        if ($response->getStatusCode() >= 400) {
            return;
        }

        if ($request->hasSession() && $request->session()->has('errors')) {
            return;
        }

        if (!$this->isMutatingRequest($request) && !$this->isExportLikeRequest($request)) {
            return;
        }

        [$title, $category, $actionType] = $this->resolveActionDetails($request, $routeName);
        $tenantContext = $request->attributes->get('tenantContext');

        $details = [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'route_name' => $routeName,
            'method' => strtoupper($request->method()),
            'path' => '/'.$request->path(),
            'ip' => $request->ip(),
            'branch_id' => $user->branch_id,
            'tenant_scope' => $tenantContext?->scopeType,
            'tenant_province_id' => $tenantContext?->provinceId,
            'tenant_barangay_id' => $tenantContext?->barangayId,
            'tenant_province_slug' => $tenantContext?->provinceSlug,
            'tenant_barangay_slug' => $tenantContext?->barangaySlug,
        ];

        foreach (['id', 'product_id', 'inventory_id', 'patientrecord_id', 'branch_id', 'supplier_id'] as $key) {
            if ($request->filled($key)) {
                $details[$key] = $request->input($key);
            }
        }

        if ($this->isExportLikeRequest($request)) {
            $details['export_format'] = $this->resolveExportFormat($request);
        }

        $this->notificationService->notify([
            'type' => $category,
            'category' => $category,
            'action_type' => $actionType,
            'title' => $title,
            'details' => $details,
        ]);
    }

    private function isMutatingRequest(Request $request): bool
    {
        return in_array(strtoupper($request->method()), self::MUTATING_METHODS, true);
    }

    private function isExportLikeRequest(Request $request): bool
    {
        $routeName = $request->route()?->getName() ?? '';
        $path = strtolower('/'.$request->path());

        if (
            str_contains($routeName, 'export')
            || str_contains($routeName, 'print')
            || str_contains($path, '/export')
            || str_contains($path, '/print')
            || str_contains($path, '/download')
        ) {
            return true;
        }

        foreach (['export', 'download', 'print'] as $queryKey) {
            if ($request->query($queryKey) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveActionDetails(Request $request, string $routeName): array
    {
        $map = [
            'admin.inventory.addproduct' => ['Product registered', 'inventory', 'create'],
            'admin.inventory.updateproduct' => ['Product updated', 'inventory', 'update'],
            'admin.inventory.archiveproduct' => ['Product archived', 'inventory', 'archive'],
            'admin.inventory.unarchiveproduct' => ['Product unarchived', 'inventory', 'restore'],
            'admin.inventory.addstock' => ['Stock added', 'inventory', 'create'],
            'admin.inventory.editstock' => ['Stock updated', 'inventory', 'update'],
            'admin.inventory.transferstock' => ['Stock transferred', 'inventory', 'transfer'],
            'admin.inventory.export' => ['Inventory exported', 'export', 'excel_export'],

            'admin.patientrecords.adddispensation' => ['Patient record created', 'patient_records', 'create'],
            'admin.patientrecords.update' => ['Patient record updated', 'patient_records', 'update'],
            'admin.patientrecords.exportPdf' => ['Patient records exported (PDF)', 'export', 'pdf_export'],
            'admin.patientrecords.exportExcel' => ['Patient records exported (Excel)', 'export', 'excel_export'],

            'admin.lowstock.global' => ['Global threshold updated', 'settings', 'update'],
            'admin.lowstock.branchDefault' => ['Branch default threshold updated', 'settings', 'update'],
            'admin.lowstock.override' => ['Low stock override updated', 'settings', 'update'],
            'admin.lowstock.override.destroy' => ['Low stock override removed', 'settings', 'delete'],

            'admin.roles.update' => ['Role permissions updated', 'settings', 'update'],
        ];

        if (isset($map[$routeName])) {
            return $map[$routeName];
        }

        if ($this->isExportLikeRequest($request)) {
            return ['Data exported', 'export', 'export'];
        }

        $label = str_replace(['.', '_', '-'], ' ', $routeName);
        $label = ucwords(trim($label));

        $method = strtoupper($request->method());
        $actionType = match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'action',
        };

        return [$label, 'system_activity', $actionType];
    }

    private function resolveExportFormat(Request $request): string
    {
        $routeName = $request->route()?->getName() ?? '';
        $path = strtolower('/'.$request->path());
        $queryExport = strtolower((string) $request->query('export', ''));

        if (str_contains($routeName, 'exportPdf') || str_contains($path, 'pdf')) {
            return 'pdf';
        }

        if (
            str_contains($routeName, 'exportExcel')
            || str_contains($routeName, 'inventory.export')
            || str_contains($path, 'excel')
            || $queryExport === 'excel'
        ) {
            return 'excel';
        }

        if (str_contains($routeName, 'print') || str_contains($path, '/print')) {
            return 'print';
        }

        return 'file';
    }
}
