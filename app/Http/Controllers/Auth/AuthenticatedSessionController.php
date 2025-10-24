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

        // --- ITO YUNG RECOMMENDED LOGIC ---
        $user = Auth::user();

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

        // Fallback
        return redirect()->route('dashboard');
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
