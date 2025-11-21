<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\Branch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewUserCredentials;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class ManageaccountController extends Controller
{
    public function showManageaccount(Request $request)
{
    $currentUser = Auth::user();

    $query = User::with(['level', 'branch']);

    if ($request->has('search') && !empty($request->search)) {
        $searchTerm = $request->search;
        $query->where(function($q) use ($searchTerm) {
            $q->where('name', 'like', '%' . $searchTerm . '%')
              ->orWhere('email', 'like', '%' . $searchTerm . '%');
        });
    }

    $users = $query->orderBy('created_at', 'desc')->paginate(10);

    if ($request->ajax()) {
        return view('admin.partials.users-table', compact('users'))->render();
    }

    $levels = UserLevel::whereIn('name', ['admin', 'doctor', 'encoder'])->get();
    $branches = Branch::all();

    return view('admin.manageaccount', compact('users', 'levels', 'branches'));
}

    public function store(Request $request)
{
    $currentUser = Auth::user();

    // 1. Validation
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'user_level_id' => 'required|exists:user_levels,id',
        'branch_id' => 'nullable|exists:branches,id', 
        'password' => [
            'required',
            'string',
            'min:8',
            'regex:/[0-9]/',      
            'regex:/[@$!%*#?&]/', 
        ],
    ]);

    // 2. Check Privileges
    $targetLevel = UserLevel::find($request->user_level_id);
    if ($currentUser->level->name !== 'superadmin' && $targetLevel->name === 'superadmin') {
            abort(403, 'You are not allowed to create a Superadmin account.');
    }

    // 3. CAPTURE RAW PASSWORD BEFORE HASHING
    // Importante ito kasi hindi natin madi-decrypt ang password pag na-hash na
    $rawPassword = $request->password;
    
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($rawPassword),
        'user_level_id' => $request->user_level_id,
        'branch_id' => $request->branch_id,
        // 'email_verified_at' => null // Default naman ito, pero sureball tayo
    ]);

    // 2. GENERATE SIGNED VERIFICATION URL
    // Ito ay gagawa ng unique link na valid lang para sa user na ito
    $verificationUrl = URL::signedRoute(
        'account.verify', 
        ['id' => $user->id]
    );

    // 3. SEND EMAIL with the URL
    $user->load(['level', 'branch']);
    try {
        // Ipasa ang $verificationUrl sa Mailable
        Mail::to($user->email)->send(new NewUserCredentials($user, $rawPassword, $verificationUrl));
    } catch (\Exception $e) {
        \Log::error('Mail Error: ' . $e->getMessage());
    }

    return redirect()->back()->with('success', 'Account created! Verification email sent.');
}
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            // Ignore current user email on update validation
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'user_level_id' => 'required|exists:user_levels,id',
            'branch_id' => 'nullable|exists:branches,id',
            'password' => 'nullable|min:8', // Password is optional on edit
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'user_level_id' => $request->user_level_id,
            'branch_id' => $request->branch_id,
        ];

        // Update password only if provided
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->back()->with('success', 'User updated successfully.');
    }
    public function verifyAccount($id)
{
    // Hanapin ang user
    $user = User::findOrFail($id);

    // Kung verified na, wag na galawin
    if (!is_null($user->email_verified_at)) {
        return redirect('/login')->with('success', 'Account is already verified. Please login.');
    }

    // I-set ang verification time
    $user->email_verified_at = Carbon::now();
    $user->save();

    // Redirect sa login page
    return redirect('/login')->with('success', 'Account successfully verified! You can now login.');
}
}   