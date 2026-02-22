<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected AuthSessionService $authSessionService
    ) {
    }

    public function create(): View|RedirectResponse
    {
        $redirectUrl = $this->authSessionService->getAuthenticatedRedirectUrl();
        if ($redirectUrl) {
            return redirect()->to($redirectUrl);
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $isTesting = app()->runningInConsole() || app()->env === 'testing';

        $request->validate([
            'g-recaptcha-response' => $isTesting ? 'nullable' : 'required|captcha',
        ], [
            'g-recaptcha-response.required' => 'Please complete the captcha verification.',
            'g-recaptcha-response.captcha' => 'Captcha verification failed, please try again.',
        ]);

        $request->authenticate();
        $request->session()->regenerate();

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $access = $this->authSessionService->canAccessApplication($user);

        if (!$access['ok']) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect($access['redirect'])->with('error', $access['error']);
        }

        $this->authSessionService->processSuccessfulLogin($user, $request->ip());

        return redirect()->to($access['redirect_url']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

