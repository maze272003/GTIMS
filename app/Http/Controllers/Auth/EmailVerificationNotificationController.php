<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function __construct(
        protected EmailVerificationFlowService $emailVerificationFlowService
    ) {
    }

    public function store(Request $request): RedirectResponse
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

        $this->emailVerificationFlowService->sendVerificationNotification($request->user());

        return back()->with('status', 'verification-link-sent');
    }
}
