@forelse($patientHotspots as $spot)
  <tr class="bg-white dark:bg-gray-800 border-b hover:bg-gray-50 dark:hover:bg-gray-700">
    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">{{ $spot->barangay }}</td>
    <td class="px-6 py-4">
      @if($spot->category == 'Senior')
        <span class="px-2 py-0.5 text-xs font-medium text-orange-700 dark:text-orange-300 bg-orange-100 dark:bg-orange-900/20 rounded-full">{{ $spot->category }}</span>
      @elseif($spot->category == 'Child')
        <span class="px-2 py-0.5 text-xs font-medium text-sky-700 dark:text-sky-300 bg-sky-100 dark:bg-sky-900/20 rounded-full">{{ $spot->category }}</span>
      @else
        <span class="px-2 py-0.5 text-xs font-medium text-lime-700 dark:text-lime-300 bg-lime-100 dark:bg-lime-900/20 rounded-full">{{ $spot->category }}</span>
      @endif
    </td>
    <td class="px-6 py-4 text-right font-bold text-gray-900 dark:text-gray-100">{{ number_format($spot->total_items) }}</td>
    <td class="px-6 py-4 text-right text-gray-600 dark:text-gray-400">{{ number_format($spot->total_patients) }}</td>
  </tr>
@empty
  <tr><td colspan="4" class="p-6 text-center text-gray-500 dark:text-gray-400">No dispensation data found for this period/filter.</td></tr>
@endforelse