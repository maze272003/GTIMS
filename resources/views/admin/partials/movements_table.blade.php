<table class="w-full text-sm text-left text-gray-500">
    <thead class="text-xs text-gray-700 uppercase bg-gray-100">
        <tr>
            <th scope="col" class="px-6 py-3">Date</th>
            <th scope="col" class="px-6 py-3">Product</th>
            <th scope="col" class="px-6 py-3">Batch #</th>
            <th scope="col" class="px-6 py-3">Type</th>
            <th scope="col" class="px-6 py-3 text-center">Qty Change</th>
            <th scope="col" class="px-6 py-3 text-center">Before</th>
            <th scope="col" class="px-6 py-3 text-center">After</th>
            <th scope="col" class="px-6 py-3">Description</th>
            <th scope="col" class="px-6 py-3">User</th>
        </tr>
    </thead>
    <tbody>
        @forelse($movements as $move)
            <tr class="bg-white border-b hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900">
                    {{ $move->created_at->format('M d, Y') }}
                    <span class="block text-xs text-gray-500">{{ $move->created_at->format('H:i A') }}</span>
                </td>
                <td class="px-6 py-4">
                    <span class="font-medium">{{ $move->product->generic_name }}</span>
                    <span class="block text-xs text-gray-500">{{ $move->product->brand_name }}</span>
                </td>
                <td class="px-6 py-4">
                    {{ $move->inventory->batch_number ?? 'N/A' }}
                </td>
                <td class="px-6 py-4">
                    @if($move->type == 'IN')
                        <span class="px-3 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">IN</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">OUT</span>
                    @endif
                </td>
                <td class="px-6 py-4 text-center font-bold text-base {{ $move->type == 'IN' ? 'text-green-600' : 'text-red-600' }}">
                    {{ $move->type == 'IN' ? '+' : '-' }}{{ number_format($move->quantity) }}
                </td>
                <td class="px-6 py-4 text-center text-gray-600">{{ number_format($move->quantity_before) }}</td>
                <td class="px-6 py-4 text-center font-medium text-gray-900">{{ number_format($move->quantity_after) }}</td>
                <td class="px-6 py-4">{{ $move->description }}</td>
                <td class="px-6 py-4">{{ $move->user->name ?? 'System' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="p-8 text-center text-gray-500">
                    No product movements found matching your filters.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- Pagination Links --}}
<div class="p-4 border-t border-gray-200">
    {{ $movements->links() }}
</div>