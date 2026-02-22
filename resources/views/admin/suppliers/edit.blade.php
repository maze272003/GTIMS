<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-20 flex justify-between items-center">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <a href="{{ route('admin.suppliers.index') }}" class="hover:underline">Suppliers</a> / <span class="text-red-700 dark:text-red-300 font-medium">Edit</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Edit Supplier: {{ $supplier->name }}</h2>
                </div>
            </div>

            @if (session('success'))
                <script>document.addEventListener('DOMContentLoaded', function() { gtToast.success(@json(session('success'))); });</script>
            @endif

            {{-- Edit Supplier Info --}}
            <form action="{{ route('admin.suppliers.update', $supplier->id) }}" method="POST">
                @csrf @method('PUT')
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-4">Supplier Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $supplier->name) }}" required class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Person</label>
                            <input type="text" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                            @error('contact_person') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                            <input type="email" name="email" value="{{ old('email', $supplier->email) }}" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                            <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                            <textarea name="address" rows="3" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">{{ old('address', $supplier->address) }}</textarea>
                            @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mb-6">
                    <a href="{{ route('admin.suppliers.index') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 dark:text-gray-300 rounded-lg bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</a>
                    <button type="submit" class="px-6 py-2.5 bg-red-700 hover:bg-red-800 text-white rounded-lg shadow-md transition">
                        <i class="fa-solid fa-save mr-1"></i> Update Supplier
                    </button>
                </div>
            </form>

            {{-- Linked Products --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Linked Products</h3>
                </div>

                {{-- Link Product Form --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                    <form action="{{ route('admin.suppliers.link-product', $supplier->id) }}" method="POST" class="flex flex-col sm:flex-row gap-4 items-end">
                        @csrf
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Product</label>
                            <select name="product_id" required class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500">
                                <option value="" disabled selected>-- Select Product --</option>
                                @foreach($products ?? [] as $product)
                                    <option value="{{ $product->id }}">{{ $product->generic_name ?? $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-full sm:w-36">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Lead Time (days)</label>
                            <input type="number" name="lead_time_days" min="0" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500" placeholder="7">
                        </div>
                        <div class="w-full sm:w-36">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unit Cost</label>
                            <input type="number" name="unit_cost" min="0" step="0.01" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500" placeholder="0.00">
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm">
                            <i class="fa-solid fa-link mr-1"></i> Link Product
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                <th class="py-3 px-4 font-medium">Product</th>
                                <th class="py-3 px-4 font-medium text-center">Lead Time (days)</th>
                                <th class="py-3 px-4 font-medium text-center">Unit Cost</th>
                                <th class="py-3 px-4 font-medium text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($supplier->products ?? [] as $product)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">{{ $product->generic_name ?? $product->name }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ $product->pivot->lead_time_days ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ $product->pivot->unit_cost ? '₱' . number_format($product->pivot->unit_cost, 2) : '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <form action="{{ route('admin.suppliers.unlink-product', [$supplier->id, $product->id]) }}" method="POST" class="inline" id="unlink-form-{{ $product->id }}">
                                            @csrf @method('DELETE')
                                            <button type="button" class="text-red-600 hover:text-red-800 transition" aria-label="Unlink product" onclick="gtConfirm({ title: 'Unlink Product?', text: 'This product will be unlinked from this supplier.', icon: 'warning', confirmText: 'Yes, unlink', onConfirm: function() { document.getElementById('unlink-form-{{ $product->id }}').submit(); } })">
                                                <i class="fa-solid fa-unlink"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No products linked to this supplier.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</x-app-layout>
