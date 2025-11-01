@forelse($urgentAlerts as $alert)
    <div class="py-2 border-b border-gray-200 dark:border-gray-700 last:border-b-0 flex justify-between items-start gap-x-3">
        <div>
            {{-- Icon based on type --}}
            @if($alert['is_expired'])
                <span class="text-red-600 dark:text-red-400 font-bold"><i class="fa-solid fa-skull-crossbones mr-1"></i> EXPIRED</span>
            @elseif($alert['type'] == 'expiring')
                <span class="text-yellow-600 dark:text-yellow-400 font-semibold"><i class="fa-regular fa-clock mr-1"></i> Expiring</span>
            @else
                <span class="text-orange-600 dark:text-orange-400 font-semibold"><i class="fa-regular fa-exclamation mr-1"></i> Low Stock</span>
            @endif

            <p class="font-medium text-sm text-gray-800 dark:text-gray-100 mt-1">{{ $alert['product_name'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $alert['batch_number'] }}</p>

            {{-- Additional Details --}}
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 space-x-2">
                 @if($alert['type'] == 'expiring' || $alert['is_expired'])
                    <span>Expires: {{ $alert['expiry_date'] }} ({{ $alert['days_until_expiry'] <= 0 ? abs($alert['days_until_expiry']).'d ago' : $alert['days_until_expiry'].'d' }})</span>
                 @endif
                 @if($alert['days_remaining_recent'] !== INF && $alert['days_remaining_recent'] <= 14)
                      <span class="font-semibold text-red-500">(~{{ number_format($alert['days_remaining_recent']) }}d left based on recent use)</span>
                 @endif
            </div>
        </div>
        {{-- Quantity --}}
        <span class="font-bold text-sm text-gray-800 dark:text-gray-100 whitespace-nowrap">
            {{ $alert['quantity'] }} left
        </span>
        {{-- You could optionally display the score for debugging: --}}
        {{-- <span class="text-xs text-gray-400">Score: {{ $alert['score'] }}</span> --}}
    </div>
@empty
    <div class="py-4 text-sm text-gray-500 dark:text-gray-400 text-center">
        <i class="fa-regular fa-check-circle text-green-500 mr-2"></i>No urgent alerts found.
    </div>
@endforelse