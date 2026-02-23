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
        if ($this->emailVerificationFlowService->hasVerifiedEmail($request->user())) {
            return redirect()->intended(route('dashboard', absolute: false) . '?verified=1');
        }

        $this->emailVerificationFlowService->verifyUser($request->user());

        return redirect()->intended(route('dashboard', absolute: false) . '?verified=1');
    }
}

