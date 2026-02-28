<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function __construct(
        protected PasswordFlowService $passwordFlowService
    ) {
    }

    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'request' => $request,
            'tenantContext' => $request->attributes->get('tenantContext'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $broker = $request->routeIs('moderator.*') ? 'moderators' : 'users';
        $payload = $request->only('email', 'password', 'password_confirmation', 'token');
        $status = $this->passwordFlowService->resetPassword($payload, $broker);
        if (
            $broker === 'moderators'
            && $status === Password::INVALID_USER
            && config('tenancy.rbac.allow_legacy_moderator_fallback', false)
        ) {
            $status = $this->passwordFlowService->resetPassword($payload, 'users');
        }

        $successRedirect = route('login');

        if ($request->route('provinceSlug') && $request->route('barangaySlug')) {
            $successRedirect = route('tenant.login', [
                'provinceSlug' => $request->route('provinceSlug'),
                'barangaySlug' => $request->route('barangaySlug'),
            ]);
        } elseif ($request->routeIs('moderator.*')) {
            $successRedirect = route('moderator.login');
        }

        return $status == Password::PASSWORD_RESET
            ? redirect()->to($successRedirect)->with('status', __($status))
            : back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
    }
}
