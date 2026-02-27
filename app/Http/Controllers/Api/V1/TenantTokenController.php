<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TenantApiTokenService;
use App\Tenancy\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantTokenController extends Controller
{
    public function __construct(
        protected TenantApiTokenService $tokenService,
        protected TenantResolver $tenantResolver,
    ) {
    }

    public function issue(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isModerator()) {
            abort(403, 'Only moderators can issue API tokens.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'province_slug' => ['required', 'string'],
            'barangay_slug' => ['nullable', 'string'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
            'ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
        ]);

        $tenantContext = !empty($validated['barangay_slug'])
            ? $this->tenantResolver->fromSlugs($validated['province_slug'], $validated['barangay_slug'])
            : $this->tenantResolver->fromProvinceSlug($validated['province_slug']);

        if (!$tenantContext || $tenantContext->isPlatform()) {
            return response()->json(['message' => 'Unable to resolve tenant context for API token.'], 422);
        }

        $created = $this->tokenService->createToken(
            $user,
            $validated['name'],
            $tenantContext,
            $validated['abilities'] ?? ['*'],
            $validated['ttl_minutes'] ?? null
        );

        return response()->json([
            'token' => $created['token'],
            'token_id' => $created['record']->id,
            'expires_at' => optional($created['record']->expires_at)?->toIso8601String(),
            'province_id' => $created['record']->province_id,
            'barangay_id' => $created['record']->barangay_id,
            'abilities' => $created['record']->abilities,
        ], 201);
    }
}

