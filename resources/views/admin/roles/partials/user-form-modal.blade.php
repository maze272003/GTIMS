@php
    $editingUserId = (int) old('editing_user_id', 0);
    $userFormMode = old('user_form_mode', 'create');
    $editingUser = $editingUserId ? $users->firstWhere('id', $editingUserId) : null;
    $userFormIsEdit = $userFormMode === 'edit' && $editingUser;
@endphp

<div
    id="userFormModal"
    class="fixed inset-0 z-[70] hidden"
    data-open-on-load="{{ !empty($shouldOpenOnLoad) ? 'true' : 'false' }}"
    data-store-url="{{ route('admin.manageaccount.store') }}"
    data-update-url-template="{{ url('/admin/manageaccount/__USER__') }}"
>
    <div id="userFormModalBackdrop" class="absolute inset-0 bg-gray-900/60 opacity-0 transition-opacity duration-200"></div>

    <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
        <div
            id="userFormModalPanel"
            class="relative w-full max-w-2xl translate-y-4 overflow-hidden rounded-3xl border border-gray-200 bg-white opacity-0 shadow-2xl transition-all duration-200 dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-red-600 dark:text-red-300">User Profile</p>
                    <h2 id="userFormModalTitle" class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                        {{ $userFormIsEdit ? 'Edit User' : 'Add User' }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">User details and permissions are managed separately for clarity.</p>
                </div>

                <button
                    type="button"
                    data-close-user-modal
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:border-red-200 hover:text-red-600 dark:border-gray-700 dark:text-gray-300 dark:hover:border-red-500/40 dark:hover:text-red-300"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form
                id="userModalForm"
                action="{{ $userFormIsEdit ? route('admin.manageaccount.update', $editingUser->id) : route('admin.manageaccount.store') }}"
                method="POST"
                class="space-y-6 px-5 py-5 sm:px-6 sm:py-6"
            >
                @csrf

                <div id="userFormMethodField">
                    @if($userFormIsEdit)
                        @method('PUT')
                    @endif
                </div>

                <input type="hidden" name="redirect_to_permissions" value="1">
                <input type="hidden" name="user_form_mode" id="userFormModeField" value="{{ $userFormMode }}">
                <input type="hidden" name="editing_user_id" id="editingUserIdField" value="{{ $userFormIsEdit ? $editingUser->id : old('editing_user_id') }}">

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="userNameField" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Full Name</label>
                        <input
                            id="userNameField"
                            type="text"
                            name="name"
                            value="{{ old('name', $userFormIsEdit ? $editingUser->name : '') }}"
                            required
                            class="block w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:bg-white focus:ring-4 focus:ring-red-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-red-400 dark:focus:bg-gray-900 dark:focus:ring-red-500/20"
                        >
                        @error('name')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="userEmailField" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Email Address</label>
                        <input
                            id="userEmailField"
                            type="email"
                            name="email"
                            value="{{ old('email', $userFormIsEdit ? $editingUser->email : '') }}"
                            required
                            class="block w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:bg-white focus:ring-4 focus:ring-red-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-red-400 dark:focus:bg-gray-900 dark:focus:ring-red-500/20"
                        >
                        @error('email')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="userRoleField" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Role</label>
                        <select
                            id="userRoleField"
                            name="user_level_id"
                            required
                            class="block w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:bg-white focus:ring-4 focus:ring-red-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-red-400 dark:focus:bg-gray-900 dark:focus:ring-red-500/20"
                        >
                            <option value="">Select role</option>
                            @foreach($levels as $level)
                                <option
                                    value="{{ $level->id }}"
                                    @selected((string) old('user_level_id', $userFormIsEdit ? $editingUser->user_level_id : '') === (string) $level->id)
                                >
                                    {{ ucfirst($level->name) }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_level_id')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="userBranchField" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Branch</label>
                        @if(auth()->user()?->level?->name === 'superadmin')
                            <select
                                id="userBranchField"
                                name="branch_id"
                                required
                                class="block w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:bg-white focus:ring-4 focus:ring-red-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-red-400 dark:focus:bg-gray-900 dark:focus:ring-red-500/20"
                            >
                                <option value="">Select branch</option>
                                @foreach($branches as $branch)
                                    <option
                                        value="{{ $branch->id }}"
                                        @selected((string) old('branch_id', $userFormIsEdit ? $editingUser->branch_id : '') === (string) $branch->id)
                                    >
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input
                                type="text"
                                value="{{ auth()->user()?->branch?->name }}"
                                disabled
                                class="block w-full rounded-2xl border border-gray-200 bg-gray-100 px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                            >
                            <input type="hidden" name="branch_id" value="{{ auth()->user()?->branch_id }}">
                        @endif
                        @error('branch_id')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <label for="userPasswordField" id="userPasswordLabel" class="block text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                {{ $userFormIsEdit ? 'New Password' : 'Password' }}
                            </label>
                            <span id="userPasswordHint" class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $userFormIsEdit ? 'Leave blank to keep the current password.' : 'Use at least 8 characters, with one number and one symbol.' }}
                            </span>
                        </div>
                        <input
                            id="userPasswordField"
                            type="password"
                            name="password"
                            @unless($userFormIsEdit) required @endunless
                            class="block w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:bg-white focus:ring-4 focus:ring-red-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-red-400 dark:focus:bg-gray-900 dark:focus:ring-red-500/20"
                        >
                        @error('password')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        data-close-user-modal
                        class="inline-flex items-center justify-center rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-red-200 hover:text-red-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-red-500/40 dark:hover:text-red-300"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-red-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-red-700"
                    >
                        <i class="fa-solid fa-floppy-disk mr-2"></i>
                        Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
