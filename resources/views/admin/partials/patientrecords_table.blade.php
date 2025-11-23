{{-- resources/views/admin/partials/patientrecords_table.blade.php --}}

<div class="overflow-x-auto p-5">
    <table class="w-full pagination-links text-sm text-left">
        <thead class="sticky top-0 bg-gray-200 dark:bg-gray-700">
            <tr>
                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">#</th>
                
                @if(in_array(auth()->user()->user_level_id, [1, 2]))
                    <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Branch</th>
                @endif

                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Resident Details</th>
                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-center tracking-wide">Resident Category</th>
                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Date Dispensed</th>
                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-center tracking-wide">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            @if ($patientrecords->isEmpty())
                <tr>
                    <td colspan="{{ in_array(auth()->user()->user_level_id, [1, 2]) ? 6 : 5 }}" class="p-3 text-center text-sm text-gray-500 dark:text-gray-400">No records found.</td>
                </tr>
            @else
                @foreach ($patientrecords as $patientrecord)
                {{-- Note: I retained your data attributes here --}}
                <tr data-record-id="{{ $patientrecord->id }}"
                    data-patient-name="{{ $patientrecord->patient_name }}"
                    data-barangay-id="{{ $patientrecord->barangay_id }}"
                    data-barangay="{{ $patientrecord->barangay->barangay_name ?? '' }}"
                    data-purok="{{ $patientrecord->purok }}"
                    data-category="{{ $patientrecord->category }}"
                    data-date-dispensed="{{ $patientrecord->date_dispensed->format('Y-m-d') }}"
                    data-medications="{{ json_encode($patientrecord->dispensedMedications->map(function ($med) {
                        return [
                            'batch' => $med->batch_number,
                            'medication' => $med->generic_name,
                            'brand' => $med->brand_name,
                            'form' => $med->form,
                            'strength' => $med->strength,
                            'quantity' => $med->quantity,
                        ];
                    })->toArray()) }}">
                    
                    <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-left">
                        {{ $loop->iteration + ($patientrecords->currentPage() - 1) * $patientrecords->perPage() }}
                    </td>

                    @if(in_array(auth()->user()->user_level_id, [1, 2]))
                        <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-left">
                            <span class="px-2 py-1 bg-gray-100 dark:bg-gray-600 rounded text-xs font-semibold">
                                {{ $patientrecord->branch->name ?? 'N/A' }}
                            </span>
                        </td>
                    @endif

                    <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-left">
                        <div>
                            <p class="font-semibold text-gray-700 dark:text-gray-200 capitalize">{{ $patientrecord->patient_name }}</p>
                            <p class="italic text-gray-500 dark:text-gray-400 capitalize">{{ $patientrecord->barangay->barangay_name ?? '' }}, {{ $patientrecord->purok }}</p>
                        </div>
                    </td>
                    <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-center">{{ $patientrecord->category }}</td>
                    <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-center">
                        <p class="font-semibold">{{ $patientrecord->date_dispensed->format('F j, Y') }}</p>
                        <p class="italic text-gray-500 dark:text-gray-400">{{ $patientrecord->created_at->format('g:i A') }}</p>
                    </td>
                    
                    <td class="p-3 flex items-center justify-center gap-2 font-semibold">
                        <button class="view-medications-btn bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-blue-600 dark:hover:bg-blue-800 hover:text-white font-semibold text-sm" data-record-id="{{ $patientrecord->id }}">
                            <i class="fa-regular fa-eye mr-1"></i>View All
                        </button>
                        @if (auth()->user()->user_level_id != 4)
                            @if(in_array(auth()->user()->user_level_id, [1, 2]) || auth()->user()->branch_id == $patientrecord->branch_id)
                                <button class="editrecordbtn bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-green-600 dark:hover:bg-green-800 hover:text-white font-semibold text-sm" data-record-id="{{ $patientrecord->id }}">
                                    <i class="fa-regular fa-pen-to-square mr-1"></i>Edit
                                </button>
                                {{-- <button class="deleterecordbtn bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-red-600 dark:hover:bg-red-800 hover:text-white font-semibold text-sm" data-record-id="{{ $patientrecord->id }}">
                                    <i class="fa-regular fa-trash mr-1"></i>Delete
                                </button> --}}
                            @endif
                        @endif
                    </td>
                </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>

{{-- Pagination Footer --}}
<div class="p-4 border-t bg-white dark:bg-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4 border-gray-200 dark:border-gray-700">
    <p class="text-sm text-gray-600 dark:text-gray-400 order-2 sm:order-1">
        Showing {{ $patientrecords->firstItem() ?? 0 }} to {{ $patientrecords->lastItem() ?? 0 }} of {{ $patientrecords->total() }} results
    </p>
    <div class="flex flex-wrap justify-center sm:justify-end gap-2 pagination-links order-1 sm:order-2 w-full sm:w-auto">
        {{ $patientrecords->links('pagination::tailwind') }} 
    </div>
</div>