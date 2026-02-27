<?php

namespace App\Services;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TenantExportService
{
    public function __construct(
        protected TenantStorageService $tenantStorageService,
    ) {
    }

    /**
     * Store export file in tenant-isolated directory and return path info.
     *
     * @return array{path:string,file_name:string,disk:string,headers:array<string,string>}
     */
    public function store(
        object $export,
        string $fileName,
        ?TenantContext $tenantContext = null,
        string $writerType = ExcelWriter::XLSX
    ): array {
        $ctx = $tenantContext ?: (app()->bound(TenantContext::class) ? app(TenantContext::class) : null);
        $disk = (string) config('tenancy.storage.disk', 'local');
        $basePath = $ctx ? $this->tenantStorageService->tenantPath('exports/' . $fileName, $ctx) : 'platform/exports/' . $fileName;

        Excel::store($export, $basePath, $disk, $writerType);

        return [
            'path' => $basePath,
            'file_name' => $fileName,
            'disk' => $disk,
            'headers' => $this->tenantHeaders($ctx),
        ];
    }

    public function download(array $stored): BinaryFileResponse
    {
        $fullPath = Storage::disk($stored['disk'])->path($stored['path']);

        return response()->download($fullPath, $stored['file_name'], $stored['headers']);
    }

    /**
     * @return array<string, string>
     */
    public function tenantHeaders(?TenantContext $tenantContext): array
    {
        if (!$tenantContext) {
            return [
                'X-Tenant-Scope' => 'platform',
            ];
        }

        return [
            'X-Tenant-Scope' => $tenantContext->scopeType,
            'X-Tenant-Province-Id' => (string) ($tenantContext->provinceId ?? ''),
            'X-Tenant-Barangay-Id' => (string) ($tenantContext->barangayId ?? ''),
            'X-Tenant-Province-Slug' => (string) ($tenantContext->provinceSlug ?? ''),
            'X-Tenant-Barangay-Slug' => (string) ($tenantContext->barangaySlug ?? ''),
        ];
    }
}

