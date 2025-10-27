<x-app-layout>
    <x-slot name="title">
        HistoryLog - General Tinio
    </x-slot>
<body class="bg-gray-50">
 
    <x-admin.sidebar/>
 
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
            <div class="mb-6 pt-16">
                <p class="text-sm text-gray-600">Home / <span class="text-red-700 font-medium">History Logs</span></p>
            </div>
 
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mt-6">
                <div class="p-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <h2 class="text-lg font-semibold text-gray-700">System Activity Timeline</h2>
                    <div class="flex gap-2">
                        <button class="px-4 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                            <i class="fas fa-filter text-xs"></i> Filter
                        </button>
                    </div>
                </div>

                <div class="p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="relative w-full md:w-1/2">
                        <i class="fa-regular fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" placeholder="Search logs..." class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-transparent text-sm transition-all">
                    </div>
                </div>
               
                <div class="overflow-x-auto p-5">
                    <table class="min-w-full table-auto border-collapse">
                        <thead class="bg-gray-200 text-gray-700 sticky top-0">
                            <tr>
                                <th class="p-4 text-gray-600 uppercase text-xs font-bold text-left tracking-wider border-b border-gray-200">#</th>
                                <th class="p-4 text-gray-600 uppercase text-xs font-bold text-left tracking-wider border-b border-gray-200">Action</th>
                                <th class="p-4 text-gray-600 uppercase text-xs font-bold text-left tracking-wider border-b border-gray-200">Description</th>
                                <th class="p-4 text-gray-600 uppercase text-xs font-bold text-left tracking-wider border-b border-gray-200">Date</th>
                                <th class="p-4 text-gray-600 uppercase text-xs font-bold text-center tracking-wider border-b border-gray-200">User</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="text-gray-700 hover:bg-gray-50 transition duration-100">
                                <td class="p-4 text-sm font-medium">1</td>
                                <td class="p-4 text-sm">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        ADD
                                    </span>
                                </td>
                                <td class="p-4 text-sm text-gray-500">
                                    Added new stock: Amoxicillin (Qty: 50 pcs.)
                                </td>
                                <td class="p-4 text-sm">January 1, 2024 09:30 AM</td>
                                <td class="p-4 text-sm text-center font-medium">Admin User</td>
                            </tr>
                            <tr class="text-gray-700 hover:bg-gray-50 transition duration-100">
                                <td class="p-4 text-sm font-medium">2</td>
                                <td class="p-4 text-sm">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        UPDATE
                                    </span>
                                </td>
                                <td class="p-4 text-sm text-gray-500">
                                    Update stock: Amoxicillin (Qty: 50 pcs.)
                                </td>
                                <td class="p-4 text-sm">October 1, 2025 10:15 AM</td>
                                <td class="p-4 text-sm text-center font-medium">Staff User</td>
                            </tr>
                            <tr class="text-gray-700 hover:bg-gray-50 transition duration-100">
                                <td class="p-4 text-sm font-medium">3</td>
                                <td class="p-4 text-sm">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        ARCHIVE
                                    </span>
                                </td>
                                <td class="p-4 text-sm text-gray-500">
                                    Archive product: Amoxicillin
                                </td>
                                <td class="p-4 text-sm">October 2, 2025 11:45 AM</td>
                                <td class="p-4 text-sm text-center font-medium">Super Admin User</td>
                            </tr>
                            <tr class="text-gray-700 hover:bg-gray-50 transition duration-100">
                                <td class="p-4 text-sm font-medium">4</td>
                                <td class="p-4 text-sm">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        LOGIN
                                    </span>
                                </td>
                                <td class="p-4 text-sm text-gray-500">
                                    User logged in successfully
                                </td>
                                <td class="p-4 text-sm">October 3, 2025 09:00 AM</td>
                                <td class="p-4 text-sm text-center font-medium">Admin User</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
 
                <div class="p-4 border-t border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <p class="text-sm text-gray-600">Showing 1 to 10 of 50 results</p>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100">Previous</button>
                        <button class="px-3 py-1 text-sm bg-red-700 text-white rounded-lg">1</button>
                        <button class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100">2</button>
                        <button class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100">Next</button>
                    </div>
                </div>
            </div>
 
        </main>
    </div>
 
</body>
</x-app-layout>