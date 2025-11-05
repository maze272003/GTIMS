@forelse($forecast as $item)
  <tr class="bg-white dark:bg-gray-800 border-b hover:bg-gray-50 dark:hover:bg-gray-700
    @if($item['days_remaining'] < 15) dark:bg-red-900/20
    @elseif($item['days_remaining'] < 30) dark:bg-yellow-900/20
    @endif">
    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
      {{ $item['product_name'] }}
      <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $item['brand_name'] }}</span>
    </td>
    <td class="px-6 py-4 text-center font-bold
        @if($item['days_remaining'] == INF) text-gray-500 dark:text-gray-400
        @elseif($item['days_remaining'] < 15) text-red-600 dark:text-red-400
        @elseif($item['days_remaining'] < 30) text-yellow-600 dark:text-yellow-400
        @else text-green-600 dark:text-green-400
        @endif">
      @if($item['days_remaining'] == INF)
        N/A
      @else
        {{ number_format($item['days_remaining']) }} days
      @endif
    </td>
    <td class="px-6 py-4 text-center text-gray-900 dark:text-gray-100">{{ number_format($item['current_stock']) }}</td>
    <td class="px-6 py-4 text-center text-gray-900 dark:text-gray-100">{{ $item['avg_daily_usage'] }}</td>
  </tr>
@empty
  <tr><td colspan="4" class="p-6 text-center text-gray-500 dark:text-gray-400">No consumption data to build a forecast.</td></tr>
@endforelse