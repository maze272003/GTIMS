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
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $result = $this->otpLoginService->sendOtp($request, (string) $request->email);

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
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $result = $this->otpLoginService->verifyOtp($request, (string) $request->email, (string) $request->otp);

        return response()->json(
            array_intersect_key($result, array_flip(['success', 'message', 'redirect_url'])),
            $result['status']
        );
    }
}
