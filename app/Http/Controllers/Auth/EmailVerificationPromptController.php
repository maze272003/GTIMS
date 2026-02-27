<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    public function __construct(
        protected EmailVerificationFlowService $emailVerificationFlowService
    ) {
    }

    public function __invoke(Request $request): RedirectResponse|View
    {
        $tenantContext = $request->attributes->get('tenantContext');

        if ($this->emailVerificationFlowService->hasVerifiedEmail($request->user())) {
            if ($tenantContext) {
                return redirect()->intended(tenant_route('tenant.dashboard', [], $tenantContext));
            }

            if ($request->routeIs('moderator.*')) {
                return redirect()->intended(route('moderator.dashboard'));
            }

            return redirect()->intended(route('dashboard', absolute: false));
        }

        return view('auth.verify-email', ['tenantContext' => $tenantContext]);
    }
}
