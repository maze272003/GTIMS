@forelse($forecast as $item)
  <tr class="bg-white border-b hover:bg-gray-50
    @if($item['days_remaining'] < 15) bg-red-50
    @elseif($item['days_remaining'] < 30) bg-yellow-50
    @endif">
    <td class="px-6 py-4 font-medium text-gray-900">
      {{ $item['product_name'] }}
      <span class="block text-xs text-gray-500">{{ $item['brand_name'] }}</span>
    </td>
    <td class="px-6 py-4 text-center font-bold
        @if($item['days_remaining'] == INF) text-gray-500
        @elseif($item['days_remaining'] < 15) text-red-600
        @elseif($item['days_remaining'] < 30) text-yellow-600
        @else text-green-600
        @endif">
      @if($item['days_remaining'] == INF)
        N/A
      @else
        {{ number_format($item['days_remaining']) }} days
      @endif
    </td>
    <td class="px-6 py-4 text-center">{{ number_format($item['current_stock']) }}</td>
    <td class="px-6 py-4 text-center">{{ $item['avg_daily_usage'] }}</td>
  </tr>
@empty
  <tr><td colspan="4" class="p-6 text-center">No consumption data to build a forecast.</td></tr>
@endforelse