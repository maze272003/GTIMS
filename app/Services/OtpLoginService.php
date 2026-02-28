<?php

namespace App\Services;

use App\Mail\SendOtpMail;
use App\Models\Moderator;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class OtpLoginService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected AuthSessionService $authSessionService,
        protected TenantResolver $tenantResolver,
    ) {
    }

    public function sendOtp(Request $request, string $email): array
    {
        [$loginMode, $tenantContext] = $this->resolveLoginModeAndContext($request);
        [$user] = $this->resolveUserAndGuard($email, $loginMode, ['level']);

        if (!$user) {
            return ['success' => false, 'status' => 422, 'message' => 'User not found.'];
        }

        $access = $this->authSessionService->canAccessApplication($user, $tenantContext, $loginMode);
        if (!$access['ok']) {
            return ['success' => false, 'status' => 403, 'message' => $access['error']];
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(5);
        $user->otp = $otp;
        $user->otp_expires_at = $expiresAt;
        $user->save();

        try {
            Mail::to($user->email)->send(new SendOtpMail($otp));
        } catch (\Throwable $e) {
            return ['success' => false, 'status' => 500, 'message' => 'Failed to send OTP email. Please check configuration.'];
        }

        return ['success' => true, 'status' => 200, 'message' => 'OTP has been sent to your email.'];
    }

    public function verifyOtp(Request $request, string $email, string $otp): array
    {
        [$loginMode, $tenantContext] = $this->resolveLoginModeAndContext($request);
        [$user, $guard] = $this->resolveUserAndGuard($email, $loginMode, ['level.permissions']);

        if (
            !$user
            || (string) $user->otp !== (string) $otp
            || !$user->otp_expires_at
            || Carbon::now()->isAfter($user->otp_expires_at)
        ) {
            return ['success' => false, 'status' => 401, 'message' => 'Invalid or expired OTP. Please try again.'];
        }

        Auth::guard($guard)->login($user);
        $request->session()->regenerate();

        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        $access = $this->authSessionService->canAccessApplication($user, $tenantContext, $loginMode);

        if (!$access['ok']) {
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ['success' => false, 'status' => 403, 'message' => $access['error']];
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

        return ['success' => true, 'status' => 200, 'redirect_url' => $access['redirect_url']];
    }

    /**
     * @return array{0: string, 1: TenantContext|null}
     */
    protected function resolveLoginModeAndContext(Request $request): array
    {
        $loginMode = (string) $request->input('login_mode', 'legacy');
        if ($request->routeIs('tenant.*') || $request->filled('provinceSlug')) {
            $loginMode = 'tenant';
        } elseif ($request->routeIs('moderator.*')) {
            $loginMode = 'moderator';
        }

        /** @var TenantContext|null $tenantContext */
        $tenantContext = $request->attributes->get('tenantContext');

        if (!$tenantContext) {
            $provinceSlug = $request->route('provinceSlug') ?: $request->input('provinceSlug');
            $barangaySlug = $request->route('barangaySlug') ?: $request->input('barangaySlug');

            if ($provinceSlug && $barangaySlug) {
                $tenantContext = $this->tenantResolver->fromSlugs((string) $provinceSlug, (string) $barangaySlug);
            }
        }

        return [$loginMode, $tenantContext];
    }

    /**
     * @param  array<int, string>  $relations
     * @return array{0: User|null, 1: string}
     */
    protected function resolveUserAndGuard(string $email, string $loginMode, array $relations = []): array
    {
        if ($loginMode === 'moderator') {
            $moderator = Moderator::query()->with($relations)->where('email', $email)->first();
            if ($moderator) {
                return [$moderator, 'moderator'];
            }

            if (!config('tenancy.rbac.allow_legacy_moderator_fallback', false)) {
                return [null, 'moderator'];
            }
        }

        return [$this->userRepository->findByEmailWithRelations($email, $relations), 'web'];
    }
}
