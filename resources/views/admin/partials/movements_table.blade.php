<table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
    <thead class="text-xs uppercase bg-gray-100 dark:bg-gray-700">
        <tr>
            <th class="px-6 py-3">Date & Time</th>
            <th class="px-6 py-3">Branch</th> <!-- NEW COLUMN -->
            <th class="px-6 py-3">Product</th>
            <th class="px-6 py-3">Batch #</th>
            <th class="px-6 py-3">Type</th>
            <th class="px-6 py-3 text-center">Qty Change</th>
            <th class="px-6 py-3 text-center">Before</th>
            <th class="px-6 py-3 text-center">After</th>
            <th class="px-6 py-3">Description</th>
            <th class="px-6 py-3">User</th>
        </tr>
    </thead>
    <tbody>
        @forelse($movements as $move)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-6 py-4 whitespace-nowrap">
                    {{ $move->created_at->format('M d, Y') }}
                    <span class="block text-xs text-gray-500">{{ $move->created_at->format('h:i A') }}</span>
                </td>
                <td class="px-6 py-4 font-semibold">
                    <span class="px-3 py-1 text-xs rounded-full w-full whitespace-nowrap bg-gray-100 text-gray-700">
                        {{ $move->inventory?->branch?->name ?? 'N/A' }}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <div class="font-medium">{{ $move->product->generic_name }}</div>
                    <div class="text-xs text-gray-500">{{ $move->product->brand_name }}</div>
                </td>
                <td class="px-6 py-4 font-mono">{{ $move->inventory->batch_number ?? 'N/A' }}</td>
                <td class="px-6 py-4">
                    @if($move->type == 'IN')
                        <span class="px-3 py-1 text-xs font-bold bg-green-100 text-green-800 rounded-full">IN</span>
                    @else
                        <span class="px-3 py-1 text-xs font-bold bg-red-100 text-red-800 rounded-full">OUT</span>
                    @endif
                </td>
                <td class="px-6 py-4 text-center font-bold text-lg {{ $move->type == 'IN' ? 'text-green-600' : 'text-red-600' }}">
                    {{ $move->type == 'IN' ? '+' : '-' }}{{ number_format($move->quantity) }}
                </td>
                <td class="px-6 py-4 text-center">{{ number_format($move->quantity_before) }}</td>
                <td class="px-6 py-4 text-center font-semibold">{{ number_format($move->quantity_after) }}</td>
                <td class="px-6 py-4 text-gray-700">{{ $move->description }}</td>
                <td class="px-6 py-4">{{ $move->user->name ?? 'System' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="p-10 text-center text-gray-500">No movements found.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="p-4 border-t">
    {{ $movements->links() }}
</div>
