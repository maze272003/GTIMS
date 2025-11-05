<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewLoginNotification;
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
        if (is_null($user->level)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/')->with('error', 'You are not authorized to access this application.');
        }

        // ================================================
        // === BAGONG "NEW LOGIN NOTIFICATION" LOGIC ===
        // ================================================
        $currentIp = $request->ip();

        // Ipadala lang ang email kung ang IP ay bago
        if ($user->last_login_ip !== $currentIp) {
            try {
                Mail::to($user->email)->send(new NewLoginNotification($currentIp));
            } catch (\Exception $e) {
                // Hayaan lang na magpatuloy ang login kahit mag-fail ang email
                // Pwede kang mag-log ng error dito kung kailangan
                \Log::error('Failed to send new login notification: ' . $e->getMessage());
            }
        }
        
        // I-update palagi ang login details
        $user->last_login_at = now();
        $user->last_login_ip = $currentIp;
        $user->save();
        // ================================================
        // === KATAPUSAN NG BAGONG LOGIC ===
        // ================================================


        if ($user->level->name == 'superadmin') {
            return redirect()->route('admin.dashboard');

        } elseif ($user->level->name == 'admin') {
            return redirect()->route('admin.dashboard');

        } elseif ($user->level->name == 'encoder') {
            return redirect()->route('admin.dashboard'); 
        }

        // Fallback for any other roles.
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
