@forelse($patientHotspots as $spot)
  <tr class="bg-white border-b hover:bg-gray-50">
    <td class="px-6 py-4 font-medium text-gray-900">{{ $spot->barangay }}</td>
    <td class="px-6 py-4">
      @if($spot->category == 'Senior')
        <span class="px-2 py-0.5 text-xs font-medium text-orange-700 bg-orange-100 rounded-full">{{ $spot->category }}</span>
      @elseif($spot->category == 'Child')
        <span class="px-2 py-0.5 text-xs font-medium text-sky-700 bg-sky-100 rounded-full">{{ $spot->category }}</span>
      @else
        <span class="px-2 py-0.5 text-xs font-medium text-lime-700 bg-lime-100 rounded-full">{{ $spot->category }}</span>
      @endif
    </td>
    <td class="px-6 py-4 text-right font-bold text-gray-900">{{ number_format($spot->total_items) }}</td>
    <td class="px-6 py-4 text-right text-gray-600">{{ number_format($spot->total_patients) }}</td>
  </tr>
@empty
  <tr><td colspan="4" class="p-6 text-center">No dispensation data found for this period/filter.</td></tr>
@endforelse
