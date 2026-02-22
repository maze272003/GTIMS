<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __construct(
        protected PasswordFlowService $passwordFlowService
    ) {
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $this->passwordFlowService->updatePassword($request->user(), $validated['password']);

        return back()->with('status', 'password-updated');
    }
}

