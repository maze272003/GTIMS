<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class OtpLoginController extends Controller
{
    /**
     * Magpadala ng OTP sa email ng user.
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Check kung may user_level_id
        if (is_null($user->user_level_id) || is_null($user->level)) {
             return response()->json(['success' => false, 'message' => 'You are not authorized to access this application.'], 403);
        }

        // Gumawa ng 6-digit OTP
        $otp = random_int(100000, 999999);
        // Itakda ang expiration (e.g., 5 minuto)
        $expires_at = Carbon::now()->addMinutes(5);

        $user->otp = $otp;
        $user->otp_expires_at = $expires_at;
        $user->save();

        // Ipadala ang email
        try {
            Mail::to($user->email)->send(new SendOtpMail($otp));
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to send OTP email. Please check configuration.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'OTP has been sent to your email.']);
    }

    /**
     * I-verify ang OTP at i-login ang user.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|numeric|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Check kung invalid ang OTP o expired na
        if (!$user || $user->otp !== $request->otp || Carbon::now()->isAfter($user->otp_expires_at)) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP. Please try again.'], 401);
        }

        // Success! I-login ang user
        Auth::login($user);
        $request->session()->regenerate();

        // Linisin ang OTP
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        // Kunin ang redirect logic mula sa iyong AuthenticatedSessionController
        $redirectUrl = $this->getRedirectUrl($user);
        if (is_null($redirectUrl)) {
             Auth::guard('web')->logout();
             $request->session()->invalidate();
             $request->session()->regenerateToken();
             return response()->json(['success' => false, 'message' => 'Your user role does not have access.'], 403);
        }

        return response()->json(['success' => true, 'redirect_url' => $redirectUrl]);
    }

    /**
     * Helper function para makuha ang tamang redirect URL base sa user level.
     * (Kinopya mula sa logic ng AuthenticatedSessionController mo)
     */
    protected function getRedirectUrl($user)
    {
        if (is_null($user->level)) {
            return null; // Hindi authorized
        }

        if (in_array($user->level->name, ['superadmin', 'admin', 'encoder'])) {
             return route('admin.dashboard');
        }

        return null; // Walang role na may access
    }
}