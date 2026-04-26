<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-20">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <span class="text-red-700 dark:text-red-300 font-medium">Suppliers</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Supplier Management</h2>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.suppliers.exportExcel') }}" class="inline-flex items-center justify-center px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg shadow-sm font-medium text-sm transition-all duration-200">
                        <i class="fa-solid fa-file-excel mr-2 text-green-600 dark:text-green-400"></i> Export Excel
                    </a>
                    <a href="{{ route('admin.suppliers.create') }}" class="inline-flex items-center justify-center px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow-sm font-medium text-sm transition-all duration-200">
                        <i class="fa-solid fa-plus mr-2"></i> Add Supplier
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="sticky top-0 bg-gray-200 dark:bg-gray-700">
                            <tr>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Name</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Contact Person</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Email</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Phone</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide text-center">Batches</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($suppliers ?? [] as $supplier)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors duration-150">
                                    <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-white">{{ $supplier->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $supplier->contact_person ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        @if($supplier->email)
                                            <a href="mailto:{{ $supplier->email }}" class="text-blue-600 hover:underline">{{ $supplier->email }}</a>
                                        @else - @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $supplier->phone ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-center">
                                        <x-badge variant="info">{{ $supplier->products_count ?? $supplier->supplierProducts->count() ?? 0 }}</x-badge>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="{{ route('admin.suppliers.edit', $supplier->id) }}" class="p-2 rounded-lg bg-blue-100 text-blue-700 text-sm hover:bg-blue-200 transition" title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fa-regular fa-folder-open text-4xl mb-3 text-gray-300"></i>
                                            <p>No suppliers found.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @isset($suppliers)
                    @if(method_exists($suppliers, 'links'))
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                            {{ $suppliers->links() }}
                        </div>
                    @endif
                @endisset
            </div>
        </main>
    </div>
</x-app-layout>
