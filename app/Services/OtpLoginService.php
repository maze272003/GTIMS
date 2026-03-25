<?php

namespace App\Services;

use App\Mail\SendOtpMail;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OtpLoginService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected AuthSessionService $authSessionService
    ) {
    }

    public function sendOtp(string $email): array
    {
        $user = $this->userRepository->findByEmailWithRelations($email, ['level']);

        if (!$user) {
            // Return generic success to prevent email enumeration
            return ['success' => true, 'status' => 200, 'message' => 'OTP has been sent to your email if an account exists.'];
        }

        if (is_null($user->user_level_id) || is_null($user->level)) {
            // Return generic success to prevent role enumeration
            return ['success' => true, 'status' => 200, 'message' => 'OTP has been sent to your email if an account exists.'];
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(5);
        $this->userRepository->updateOtp($user->id, $otp, $expiresAt);

        try {
            Mail::to($user->email)->send(new SendOtpMail($otp));
        } catch (\Throwable $e) {
            return ['success' => false, 'status' => 500, 'message' => 'Failed to send OTP email. Please check configuration.'];
        }

        return ['success' => true, 'status' => 200, 'message' => 'OTP has been sent to your email.'];
    }

    public function verifyOtp(Request $request, string $email, string $otp): array
    {
        $user = $this->userRepository->findByEmailWithRelations($email, ['permissions', 'level.permissions']);

        if (
            !$user
            || !$user->otp
            || !Hash::check($otp, $user->otp)
            || !$user->otp_expires_at
            || Carbon::now()->isAfter($user->otp_expires_at)
        ) {
            return ['success' => false, 'status' => 401, 'message' => 'Invalid or expired OTP. Please try again.'];
        }

        Auth::login($user);
        $request->session()->regenerate();

        $this->userRepository->updateOtp($user->id, null, null);

        $redirectUrl = $this->authSessionService->getRedirectUrl($user);
        if (!$redirectUrl) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ['success' => false, 'status' => 403, 'message' => 'Your user role does not have access.'];
        }

        return ['success' => true, 'status' => 200, 'redirect_url' => $redirectUrl];
    }
}
