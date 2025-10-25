<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // If user_level_id is null, $user->level will be null.
        // Immediately log out the user and end their session.
        if (is_null($user->level)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Redirect to the homepage with an error message.
            return redirect('/')->with('error', 'You are not authorized to access this application.');
        }

        if ($user->level->name == 'superadmin') {
            return redirect()->route('admin.dashboard');

        } elseif ($user->level->name == 'admin') {
            return redirect()->route('admin.dashboard');

        } elseif ($user->level->name == 'encoder') {
            // Siguraduhin mong may route ka para sa 'encoder.dashboard'
            // return redirect()->route('encoder.dashboard');
            
            // Kung wala pa, sa default dashboard muna
            return redirect()->route('admin.dashboard'); 
        }

        // Fallback for any other roles. Log them out and redirect.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('error', 'Your user role does not have access.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
