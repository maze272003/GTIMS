<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function __construct(
        protected PasswordFlowService $passwordFlowService
    ) {
    }

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $provinceSlug = $request->route('provinceSlug');
        $barangaySlug = $request->route('barangaySlug');
        if ($provinceSlug && $barangaySlug) {
            $request->session()->put('tenant.route_slug_province', $provinceSlug);
            $request->session()->put('tenant.route_slug_barangay', $barangaySlug);
        }

        $broker = $request->routeIs('moderator.*') ? 'moderators' : 'users';
        $status = $this->passwordFlowService->sendResetLink($request->only('email'), $broker);
        if (
            $broker === 'moderators'
            && $status === Password::INVALID_USER
            && config('tenancy.rbac.allow_legacy_moderator_fallback', false)
        ) {
            $status = $this->passwordFlowService->sendResetLink($request->only('email'), 'users');
        }

        return $status == Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
    }
}
