<?php

namespace App\Services;

use App\Mail\NewUserCredentials;
use App\Repositories\Interfaces\ManageAccountRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

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

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'user_level_id' => 'required|exists:user_levels,id',
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false))],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
            ],
        ]);

        $targetLevel = $this->manageAccountRepository->findUserLevelOrFail((int) $request->user_level_id);
        $targetLevel->loadMissing('permissions');

        if (!$currentUser->hasPermission('settings.roles') && $targetLevel->name === 'superadmin') {
            abort(403, 'You are not allowed to create a Superadmin account.');
        }

        $rawPassword = $request->password;

        $user = $this->manageAccountRepository->createUser([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($rawPassword),
            'user_level_id' => $request->user_level_id,
            'branch_id' => $request->branch_id,
            'uses_custom_permissions' => true,
        ]);

        $user->permissions()->sync($targetLevel->permissions->pluck('id')->all());

        $verificationUrl = URL::signedRoute('account.verify', ['id' => $user->id]);

        $user->load(['level', 'branch']);

        try {
            Mail::to($user->email)->send(new NewUserCredentials($user, $verificationUrl));
        } catch (\Exception $e) {
            \Log::error('Mail Error: '.$e->getMessage());
        }

        return $this->redirectAfterSave($request, $user->id, 'Account created! Verification email sent.');
    }

    public function update(Request $request, $id)
    {
        $user = $this->manageAccountRepository->findUserOrFail((int) $id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'user_level_id' => 'required|exists:user_levels,id',
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false))],
            'password' => 'nullable|min:8',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'user_level_id' => $request->user_level_id,
            'branch_id' => $request->branch_id,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $this->manageAccountRepository->updateUser($user, $data);

        return $this->redirectAfterSave($request, $user->id, 'User updated successfully.');
    }

    public function verifyAccount($id)
    {
        $user = $this->manageAccountRepository->findUserOrFail((int) $id);

        if (!is_null($user->email_verified_at)) {
            return redirect('/login')->with('success', 'Account is already verified. Please login.');
        }

        $this->manageAccountRepository->markUserVerifiedNow($user);

        return redirect('/login')->with('success', 'Account successfully verified! You can now login.');
    }

    protected function redirectAfterSave(Request $request, int $userId, string $message)
    {
        if ($request->boolean('redirect_to_permissions')) {
            return redirect()
                ->route('admin.roles.index', ['user' => $userId])
                ->with('success', $message);
        }

        return redirect()->back()->with('success', $message);
    }
}
