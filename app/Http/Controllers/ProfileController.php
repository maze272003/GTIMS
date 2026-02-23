<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ProfileAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileAccountService $profileAccountService
    ) {
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', $this->profileAccountService->getEditData($request->user()));
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $this->profileAccountService->updateProfile($request->user(), $request->validated());

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();
        $this->profileAccountService->deleteAccount($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}

