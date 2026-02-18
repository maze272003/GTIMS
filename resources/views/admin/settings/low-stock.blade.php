<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 mt-20">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Home / Settings / <span class="text-red-700 dark:text-red-300 font-medium">Low Stock Thresholds</span>
                </p>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-5">Low Stock Threshold Settings</h2>
            </div>

            @if (session('success'))
                <div id="successAlert" class="fixed top-24 right-5 border-l-4 border-green-500 bg-white text-green-700 py-3 px-6 rounded-lg shadow-lg z-50 flex items-center gap-3">
                    <i class="fa-solid fa-circle-check text-2xl"></i>
                    <div><p class="font-bold">Success!</p><p class="text-black">{{ session('success') }}</p></div>
                </div>
                <script>setTimeout(() => { const a = document.getElementById('successAlert'); if (a) a.remove(); }, 4000);</script>
            @endif

            {{-- Global Threshold --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-4">Global Threshold</h3>
                <form action="{{ route('admin.settings.low-stock.update-global') }}" method="POST" class="flex flex-col sm:flex-row gap-4 items-end">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Low Stock Threshold</label>
                        <input type="number" name="global_threshold" min="1" value="{{ $globalThreshold ?? 100 }}" required class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Items below this quantity will trigger low stock alerts.</p>
                    </div>
                    <button type="submit" class="bg-red-700 hover:bg-red-800 text-white px-4 py-2.5 rounded-lg text-sm transition shadow-sm">
                        <i class="fa-solid fa-save mr-1"></i> Save
                    </button>
                </form>
            </div>

            {{-- Per-Item Overrides --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Per-Item Overrides</h3>
                </div>

                {{-- Add Override Form --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                    <form action="{{ route('admin.settings.low-stock.add-override') }}" method="POST" class="flex flex-col sm:flex-row gap-4 items-end">
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
                        <div class="w-full sm:w-40">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Threshold</label>
                            <input type="number" name="threshold" min="1" required class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500">
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm">
                            <i class="fa-solid fa-plus mr-1"></i> Add Override
                        </button>
                    </form>
                </div>

                {{-- Overrides Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                <th class="py-3 px-4 font-medium">Product</th>
                                <th class="py-3 px-4 font-medium text-center">Custom Threshold</th>
                                <th class="py-3 px-4 font-medium text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($overrides ?? [] as $override)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">{{ $override->product->generic_name ?? $override->product->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-center font-bold text-gray-900 dark:text-white">{{ $override->threshold }}</td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <form action="{{ route('admin.settings.low-stock.remove-override', $override->id) }}" method="POST" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 transition" onclick="return confirm('Remove this override?')">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No per-item overrides configured.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Current Low Stock Alerts --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-orange-500"></i>
                        Current Low Stock Alerts
                        @if(isset($lowStockItems) && $lowStockItems->count() > 0)
                            <span class="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 text-xs font-medium px-2.5 py-0.5 rounded-full">{{ $lowStockItems->count() }}</span>
                        @endif
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                <th class="py-3 px-4 font-medium">Product</th>
                                <th class="py-3 px-4 font-medium text-center">Current Stock</th>
                                <th class="py-3 px-4 font-medium text-center">Threshold</th>
                                <th class="py-3 px-4 font-medium text-center">Deficit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($lowStockItems ?? [] as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">{{ $item->product_name ?? $item->generic_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-center font-bold text-red-600 dark:text-red-400">{{ $item->current_stock ?? 0 }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ $item->threshold ?? $globalThreshold ?? 100 }}</td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <span class="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                            -{{ ($item->threshold ?? $globalThreshold ?? 100) - ($item->current_stock ?? 0) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center">
                                            <i class="fa-solid fa-check-circle text-3xl text-green-400 mb-2"></i>
                                            <p>All stock levels are healthy!</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</x-app-layout>
