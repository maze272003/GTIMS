<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\User;
use App\Repositories\Interfaces\ManageAccountRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewUserCredentials;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\URL;

class ManageAccountAdminService
{
    public function __construct(
        protected ManageAccountRepositoryInterface $manageAccountRepository
    ) {
    }

    public function showManageaccount(Request $request)
{
    $currentUser = Auth::user();
    $users = $this->manageAccountRepository->paginateUsersWithRelations($request->input('search'), 10);

    if ($request->ajax()) {
        return view('admin.partials.users-table', compact('users'))->render();
    }

    $levels = $this->manageAccountRepository->getLevelsForManage($currentUser->hasPermission('settings.roles'));
    $branches = $this->manageAccountRepository->getAllBranches();

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

    // 2. Check Privileges — only users with settings.roles permission can create superadmins
    $targetLevel = $this->manageAccountRepository->findUserLevelOrFail((int) $request->user_level_id);
    if (!$currentUser->hasPermission('settings.roles') && $targetLevel->name === 'superadmin') {
            abort(403, 'You are not allowed to create a Superadmin account.');
    }

    // 3. CAPTURE RAW PASSWORD BEFORE HASHING
    // Importante ito kasi hindi natin madi-decrypt ang password pag na-hash na
    $rawPassword = $request->password;
    
    $user = $this->manageAccountRepository->createUser([
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
        $user = $this->manageAccountRepository->findUserOrFail((int) $id);
        
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

        $this->manageAccountRepository->updateUser($user, $data);

        return redirect()->back()->with('success', 'User updated successfully.');
    }
public function verifyAccount($id)
{
    // Hanapin ang user
    $user = $this->manageAccountRepository->findUserOrFail((int) $id);

    // Kung verified na, wag na galawin
    if (!is_null($user->email_verified_at)) {
        return redirect('/login')->with('success', 'Account is already verified. Please login.');
    }

    // I-set ang verification time
    $this->manageAccountRepository->markUserVerifiedNow($user);

    // Redirect sa login page
    return redirect('/login')->with('success', 'Account successfully verified! You can now login.');
}
}   
