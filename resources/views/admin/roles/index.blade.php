<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 mt-20">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Home / <span class="text-red-700 dark:text-red-300 font-medium">Roles & Permissions</span>
                </p>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-5">Roles & Permissions Matrix</h2>
            </div>

            @if (session('success'))
                <script>document.addEventListener('DOMContentLoaded', function() { gtToast.success(@json(session('success'))); });</script>
            @endif

            <form action="{{ route('admin.roles.update') }}" method="POST" id="rolesForm">
                @csrf
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-gray-200 dark:bg-gray-700">
                                <tr>
                                    <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide min-w-[200px] sticky left-0 bg-gray-200 dark:bg-gray-700 z-10">Permission</th>
                                    @foreach($roles ?? [] as $role)
                                        <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide text-center min-w-[120px]">{{ $role->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse($permissions ?? [] as $permission)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors duration-150">
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium sticky left-0 bg-white dark:bg-gray-800 z-10">
                                            <div class="flex flex-col">
                                                <span>{{ ucwords(str_replace(['_', '.'], ' ', $permission->name)) }}</span>
                                                @if($permission->description ?? null)
                                                    <span class="text-xs text-gray-400">{{ $permission->description }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        @foreach($roles ?? [] as $role)
                                            <td class="px-4 py-3 text-center">
                                                <input type="checkbox"
                                                    name="permissions[{{ $role->id }}][]"
                                                    value="{{ $permission->id }}"
                                                    class="w-4 h-4 text-red-600 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-red-500 cursor-pointer"
                                                    {{ $role->permissions->contains('id', $permission->id) ? 'checked' : '' }}>
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 1 + (isset($roles) ? count($roles) : 0) }}" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fa-regular fa-shield text-4xl mb-3 text-gray-300"></i>
                                                <p>No permissions configured.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if(isset($roles) && isset($permissions) && count($roles) > 0 && count($permissions) > 0)
                    <div class="flex justify-end gap-3 pb-10">
                        <button type="button" id="submitRolesBtn" class="px-6 py-2.5 bg-red-700 hover:bg-red-800 text-white rounded-lg shadow-md transition">
                            <i class="fa-solid fa-save mr-1"></i> Update Permissions
                        </button>
                    </div>
                @endif
            </form>

        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('submitRolesBtn');
        if (btn) {
            btn.addEventListener('click', function () {
                Swal.fire({
                    title: 'Update Permissions?',
                    text: 'This will update role permissions for all users.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Update',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        document.getElementById('rolesForm').submit();
                    }
                });
            });
        }
    });
    </script>
</x-app-layout>
