<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthSessionService;
use App\Services\TenantInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantInvitationController extends Controller
{
    public function __construct(
        protected TenantInvitationService $invitationService,
        protected AuthSessionService $authSessionService,
    ) {
    }

    public function accept(Request $request, string $provinceSlug, string $barangaySlug, string $token): RedirectResponse
    {
        $tenantContext = $request->attributes->get('tenantContext');

        if (!$request->user()) {
            return redirect()->route('tenant.login', [
                'provinceSlug' => $provinceSlug,
                'barangaySlug' => $barangaySlug,
            ])->with('status', 'Please log in to accept your invitation.');
        }

        $this->invitationService->accept($token, $request->user());

        if ($tenantContext) {
            foreach ($tenantContext->toSessionData() as $key => $value) {
                $request->session()->put($key, $value);
            }
        }

        $redirect = $this->authSessionService->getRedirectUrl($request->user(), $tenantContext, 'tenant')
            ?? route('tenant.dashboard', [
                'provinceSlug' => $provinceSlug,
                'barangaySlug' => $barangaySlug,
            ]);

        return redirect()->to($redirect)->with('status', 'Invitation accepted successfully.');
    }
}

