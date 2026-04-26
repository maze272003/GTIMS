<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OtpLoginController extends Controller
{
    public function __construct(
        protected OtpLoginService $otpLoginService
    ) {
    }

    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Please provide a valid email address.'], 422);
        }

        $result = $this->otpLoginService->sendOtp((string) $request->email);

        // Return generic message to prevent email enumeration
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to send OTP. Please check your email address or try again later.',
            ], $result['status']);
        }

        return response()->json(
            array_intersect_key($result, array_flip(['success', 'message', 'redirect_url'])),
            $result['status']
        );
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|numeric|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Please provide valid credentials.'], 422);
        }

        $result = $this->otpLoginService->verifyOtp($request, (string) $request->email, (string) $request->otp);

        return response()->json(
            array_intersect_key($result, array_flip(['success', 'message', 'redirect_url'])),
            $result['status']
        );
    }
}

