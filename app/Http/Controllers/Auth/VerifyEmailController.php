<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationFlowService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __construct(
        protected EmailVerificationFlowService $emailVerificationFlowService
    ) {
    }

    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $tenantContext = $request->attributes->get('tenantContext');
        $redirectUrl = route('dashboard', absolute: false) . '?verified=1';
        if ($tenantContext) {
            $redirectUrl = tenant_route('tenant.dashboard', [], $tenantContext) . '?verified=1';
        } elseif ($request->routeIs('moderator.*')) {
            $redirectUrl = route('moderator.dashboard') . '?verified=1';
        }

        if ($this->emailVerificationFlowService->hasVerifiedEmail($request->user())) {
            return redirect()->intended($redirectUrl);
        }

        $this->emailVerificationFlowService->verifyUser($request->user());

        return redirect()->intended($redirectUrl);
    }
}
