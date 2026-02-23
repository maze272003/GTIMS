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
        return $this->emailVerificationFlowService->hasVerifiedEmail($request->user())
            ? redirect()->intended(route('dashboard', absolute: false))
            : view('auth.verify-email');
    }
}

