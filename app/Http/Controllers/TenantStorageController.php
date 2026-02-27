<?php

namespace App\Http\Controllers;

use App\Services\TenantStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantStorageController extends Controller
{
    public function __construct(
        protected TenantStorageService $tenantStorageService,
    ) {
    }

    public function download(Request $request): StreamedResponse
    {
        $tenantContext = $request->attributes->get('tenantContext');
        abort_unless($tenantContext, 403, 'Tenant context is required.');

        $encodedPath = (string) $request->query('path', '');
        $fullPath = base64_decode($encodedPath, true);
        abort_if(!$fullPath, 404, 'File not found.');

        abort_unless(
            $this->tenantStorageService->belongsToTenant($fullPath, $tenantContext),
            403,
            'Unauthorized file access.'
        );

        $disk = Storage::disk((string) config('tenancy.storage.disk', 'local'));
        abort_unless($disk->exists($fullPath), 404, 'File not found.');

        return $disk->download($fullPath);
    }
}

