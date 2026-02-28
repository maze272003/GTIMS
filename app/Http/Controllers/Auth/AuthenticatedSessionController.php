<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthSessionService;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected AuthSessionService $authSessionService,
        protected TenantResolver $tenantResolver,
    ) {
    }

    public function create(Request $request): View|RedirectResponse
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');
        $isModerator = $request->routeIs('moderator.*') || str_starts_with(trim($request->path(), '/'), 'moderator');
        $loginMode = $tenantContext ? 'tenant' : ($isModerator ? 'moderator' : 'legacy');

        $redirectUrl = $this->authSessionService->getAuthenticatedRedirectUrl($tenantContext, $loginMode);
        if ($redirectUrl) {
            return redirect()->to($redirectUrl);
        }

        $routeParams = $tenantContext ? [
            'provinceSlug' => $tenantContext->provinceSlug,
            'barangaySlug' => $tenantContext->barangaySlug,
        ] : [];

        return view('auth.login', [
            'tenantContext' => $tenantContext,
            'isModerator' => $isModerator,
            'loginPostRoute' => $tenantContext
                ? route('tenant.login.submit', $routeParams)
                : ($isModerator ? route('moderator.login.submit') : route('login')),
            'passwordRequestRoute' => $tenantContext
                ? route('tenant.password.request', $routeParams)
                : ($isModerator ? route('moderator.password.request') : route('password.request')),
        ]);
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

        $guard = $this->resolveAuthenticatedGuard($request);
        /** @var \App\Models\User|null $user */
        $user = Auth::guard($guard)->user();
        if (!$user) {
            Log::warning('Authentication succeeded but no user was resolved from guard.', [
                'guard' => $guard,
                'path' => $request->path(),
            ]);

            return back()->withErrors([
                'email' => trans('auth.failed'),
            ]);
        }

        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');
        if (!$tenantContext && $request->route('provinceSlug') && $request->route('barangaySlug')) {
            $tenantContext = $this->tenantResolver->fromSlugs(
                (string) $request->route('provinceSlug'),
                (string) $request->route('barangaySlug')
            );
        }

        $loginMode = $request->routeIs('tenant.*')
            ? 'tenant'
            : ($request->routeIs('moderator.*') ? 'moderator' : 'legacy');

        $access = $this->authSessionService->canAccessApplication($user, $tenantContext, $loginMode);

        if (!$access['ok']) {
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect($access['redirect'])->with('error', $access['error']);
        }

        if ($loginMode === 'tenant' && $tenantContext) {
            foreach ($tenantContext->toSessionData() as $key => $value) {
                $request->session()->put($key, $value);
            }
        }

        if ($loginMode === 'moderator') {
            foreach (config('tenancy.session_keys', []) as $sessionKey) {
                $request->session()->forget($sessionKey);
            }
        }

        $this->authSessionService->processSuccessfulLogin($user, $request->ip(), $tenantContext);

        return redirect()->to($access['redirect_url']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        foreach (['web', 'moderator'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        foreach (config('tenancy.session_keys', []) as $sessionKey) {
            $request->session()->forget($sessionKey);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    protected function resolveAuthenticatedGuard(Request $request): string
    {
        if ($request->routeIs('moderator.*') && Auth::guard('moderator')->check()) {
            return 'moderator';
        }

        return 'web';
    }
}
