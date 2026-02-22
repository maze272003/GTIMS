<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
           
            {{-- Header Section --}}
            <div class="mb-6 pt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-20">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <span class="text-red-700 dark:text-red-300 font-medium">Replenish Orders</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Order Management</h2>
                </div>
                {{-- Create Button: Only for users with orders.create permission --}}
                @if(auth()->user()->hasPermission('orders.create'))
                    <a href="{{ route('admin.orders.create') }}" class="inline-flex items-center justify-center px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow-sm font-medium text-sm transition-all duration-200">
                        <i class="fa-solid fa-plus mr-2"></i> Create New Order
                    </a>
                @endif
            </div>

            {{-- Orders Table --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="sticky top-0 bg-gray-200 dark:bg-gray-700">
                            <tr>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Order ID</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Branch</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Requester</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Order Details (Items)</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Status</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-center tracking-wide">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($orders as $order)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors duration-150">
                                    <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-white">
                                        #{{ $order->id }}
                                        <div class="text-xs font-normal text-gray-500">{{ $order->created_at->format('M d, Y') }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $order->branch->name }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $order->user->name }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        <details class="group cursor-pointer">
                                            <summary class="list-none text-blue-600 hover:text-blue-800 font-medium text-xs flex items-center">
                                                <span>View {{ $order->items->count() }} Items</span>
                                                <i class="fa-solid fa-chevron-down ml-1 text-[10px] transition-transform group-open:rotate-180"></i>
                                            </summary>
                                            <div class="mt-2 p-3 bg-gray-50 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700 text-xs">
                                                <ul class="space-y-1">
                                                    @foreach($order->items as $item)
                                                        <li class="flex justify-between">
                                                            <span>{{ $item->product->generic_name }}</span>
                                                            <span class="font-bold">x{{ $item->quantity_requested }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                                @if($order->remarks)
                                                    <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700 italic text-gray-500">
                                                        "{{ $order->remarks }}"
                                                    </div>
                                                @endif
                                            </div>
                                        </details>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($order->status == 'pending_admin')
                                            <x-badge variant="warning">
                                                <span class="w-2 h-2 mr-1 bg-yellow-500 rounded-full animate-pulse"></span>
                                                Waiting Admin
                                            </x-badge>
                                        @elseif($order->status == 'pending_finance')
                                            <x-badge variant="info">
                                                <span class="w-2 h-2 mr-1 bg-blue-500 rounded-full animate-pulse"></span>
                                                Waiting Finance
                                            </x-badge>
                                        @elseif($order->status == 'approved')
                                            <x-badge variant="success">Approved</x-badge>
                                        @elseif($order->status == 'rejected')
                                            <x-badge variant="danger">Rejected</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">

                                            {{-- Admin Approval Actions --}}
                                            @if(Auth::user()->hasPermission('orders.approve_admin') && $order->status == 'pending_admin')
                                                <form action="{{ route('admin.orders.update', $order->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="button" class="approve-btn p-2 rounded-lg bg-green-100 text-green-700 bold text-sm hover:bg-green-200 transition" title="Approve">
                                                        <i class="fa-regular fa-check mr-1"></i>
                                                        Approve
                                                    </button>
                                                </form>

                                                <form action="{{ route('admin.orders.update', $order->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="button" class="reject-btn p-2 rounded-lg bg-red-100 text-red-700 text-sm hover:bg-red-200 transition" title="Reject">
                                                        <i class="fa-regular fa-xmark mr-1"></i>
                                                        Reject
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Finance Approval Actions --}}
                                            @if(Auth::user()->hasPermission('orders.approve_finance') && $order->status == 'pending_finance')
                                                <form action="{{ route('admin.orders.update', $order->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="button" class="approve-finance-btn p-2 rounded-lg bg-green-100 text-green-700 bold text-sm hover:bg-green-200 transition"  title="Final Approve">
                                                        <i class="fa-regular fa-check mr-1"></i>
                                                        Approve
                                                    </button>
                                                </form>

                                                <form action="{{ route('admin.orders.update', $order->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="button" class="reject-finance-btn p-2 rounded-lg bg-red-100 text-red-700 text-sm hover:bg-red-200 transition" title="Reject">
                                                        <i class="fa-regular fa-xmark mr-1"></i>
                                                        Reject
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Print Action --}}
                                            @if($order->status == 'approved')
                                                <a href="{{ route('admin.orders.print', $order->id) }}" target="_blank" class="p-2 rounded-lg text-sm bold bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="Print PDF">
                                                    <i class="fa-regular fa-file-pdf mr-1"></i>
                                                    Print
                                                </a>
                                            @endif

                                            {{-- No Actions --}}
                                            @if((!Auth::user()->hasPermission('orders.approve_admin') && !Auth::user()->hasPermission('orders.approve_finance')) || ($order->status !== 'pending_admin' && $order->status !== 'pending_finance' && $order->status !== 'approved'))
                                                <span class="text-xs text-gray-400">-</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fa-regular fa-folder-open text-4xl mb-3 text-gray-300"></i>
                                            <p>No orders found.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $orders->links() }}
                </div>
            </div>
        </main>
    </div>

    {{-- SweetAlert Confirmation for All Actions --}}
    <script>
        document.querySelectorAll('.approve-btn, .approve-finance-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('form');
                Swal.fire({
                    title: 'Approve Order?',
                    text: "This order will be approved and processed.",
                    icon: 'info',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel',
                    confirmButtonText: 'Confirm',
                    allowOutsideClick: false,
                    customClass: {
                        container: 'swal-container',
                        popup: 'swal-popup',
                        title: 'swal-title',
                        htmlContainer: 'swal-content',
                        confirmButton: 'swal-confirm-button',
                        cancelButton: 'swal-cancel-button',
                        icon: 'swal-icon'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Processing...',
                            customClass: {
                                container: 'swal-container',
                                popup: 'swal-popup',
                                title: 'swal-title',
                                htmlContainer: 'swal-content',
                                cancelButton: 'swal-cancel-button',
                                icon: 'swal-icon'
                            },
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        form.submit();
                    }
                });
            });
        });

        document.querySelectorAll('.reject-btn, .reject-finance-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('form');
                Swal.fire({
                    title: 'Reject Order?',
                    text: "This order will be rejected and returned.",
                    icon: 'info',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel',
                    confirmButtonText: 'Confirm',
                    allowOutsideClick: false,
                    customClass: {
                        container: 'swal-container',
                        popup: 'swal-popup',
                        title: 'swal-title',
                        htmlContainer: 'swal-content',
                        confirmButton: 'swal-confirm-button',
                        cancelButton: 'swal-cancel-button',
                        icon: 'swal-icon'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Processing...',
                            allowOutsideClick: false,
                            customClass: {
                                container: 'swal-container',
                                popup: 'swal-popup',
                                title: 'swal-title',
                                htmlContainer: 'swal-content',
                                cancelButton: 'swal-cancel-button',
                                icon: 'swal-icon'
                            },
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        form.submit();
                    }
                });
            });
        });
    </script>
</x-app-layout>