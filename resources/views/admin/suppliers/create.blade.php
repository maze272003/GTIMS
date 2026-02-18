<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-20 flex justify-between items-center">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <a href="{{ route('admin.suppliers.index') }}" class="hover:underline">Suppliers</a> / <span class="text-red-700 dark:text-red-300 font-medium">Create</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Add Supplier</h2>
                </div>
            </div>

            <form action="{{ route('admin.suppliers.store') }}" method="POST">
                @csrf
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-4">Supplier Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500" placeholder="Supplier name">
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Person</label>
                            <input type="text" name="contact_person" value="{{ old('contact_person') }}" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500" placeholder="Contact person name">
                            @error('contact_person') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500" placeholder="supplier@example.com">
                            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500" placeholder="+63 XXX XXX XXXX">
                            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                            <textarea name="address" rows="3" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500" placeholder="Full address...">{{ old('address') }}</textarea>
                            @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pb-10">
                    <a href="{{ route('admin.suppliers.index') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 dark:text-gray-300 rounded-lg bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</a>
                    <button type="submit" class="px-6 py-2.5 bg-red-700 hover:bg-red-800 text-white rounded-lg shadow-md transition">
                        <i class="fa-solid fa-save mr-1"></i> Save Supplier
                    </button>
                </div>
            </form>

        </main>
    </div>
</x-app-layout>
