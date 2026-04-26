<div class="overflow-x-auto p-5">
    <table class="w-full text-sm text-left">
        <thead class="sticky top-0 bg-gray-200 dark:bg-gray-700">
            <tr>
                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">#</th>
                
                @if(auth()->user()->hasPermission('patients.manage'))
                    <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Branch</th>
                @endif
                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase font-bold">Resident Details</th>
                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase font-bold text-center">Category</th>
                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase font-bold">Date Dispensed</th>
                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase font-bold text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            @if ($patientrecords->isEmpty())
                <tr>
                    <td colspan="{{ auth()->user()->hasPermission('patients.manage') ? 6 : 5 }}" class="p-3 text-center text-sm text-gray-500 dark:text-gray-400">No records found.</td>
                </tr>
            @else
                @foreach ($patientrecords as $patientrecord)
                @php
                    $editRecordState = $permissionView->disabledAttributes('patients.manage', 'edit patient records');
                    $editRecordClasses = $permissionView->disabledClasses('patients.manage');
                @endphp
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
                            'batch' => $m->batch_number,
                            'medication' => $m->generic_name,
                            'brand' => $m->brand_name,
                            'form' => $m->form,
                            'strength' => $m->strength,
                            'quantity' => $m->quantity
                        ];
                    })->toArray()) }}">
                    
                    <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-left">
                        {{ $loop->iteration + ($patientrecords->currentPage() - 1) * $patientrecords->perPage() }}
                    </td>

                    @if(auth()->user()->hasPermission('patients.manage'))
                        <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-left">
                            <span class="px-2 py-1 bg-gray-100 dark:bg-gray-600 rounded text-xs font-semibold">
                                {{ $patientrecord->branch->name ?? 'N/A' }}
                            </span>
                        </td>
                    @endif

                    <td class="p-3">
                        <p class="font-bold text-gray-800 dark:text-gray-200 capitalize">{{ $record->patient_name }}</p>
                        <p class="text-xs text-gray-500 capitalize">{{ $record->barangay->barangay_name ?? '' }}, {{ $record->purok }}</p>
                    </td>
                    
                    <td class="p-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold 
                            {{ $record->category == 'Senior' ? 'bg-orange-100 text-orange-700' : 
                              ($record->category == 'Child' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700') }}">
                            {{ $record->category }}
                        </span>
                    </td>

                    <td class="p-3">
                        <p class="font-medium">{{ $record->date_dispensed->format('M d, Y') }}</p>
                        <p class="text-xs text-gray-400">{{ $record->created_at->format('h:i A') }}</p>
                    </td>

                    <td class="p-3 flex justify-center gap-2">
                        {{-- View Button --}}
                        <button type="button" class="view-medications-btn bg-blue-100 text-blue-700 p-2 rounded hover:bg-blue-600 hover:text-white transition">
                            <i class="fa-regular fa-eye mr-1"></i> View
                        </button>
                        <button
                            type="button"
                            {!! $editRecordState !!}
                            class="editrecordbtn bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 p-2 rounded-xl hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-green-600 dark:hover:bg-green-800 hover:text-white font-semibold text-sm {{ $editRecordClasses }}"
                            data-record-id="{{ $patientrecord->id }}"
                        >
                            <i class="fa-regular fa-pen-to-square mr-1"></i>Edit
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="p-6 text-center text-gray-500">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- PAGINATION --}}
{{-- IMPORTANT: The wrapper class "pagination-links" is required for JS --}}
<div class="p-4 border-t bg-white dark:bg-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4">
    <p class="text-sm text-gray-600">
        Showing {{ $patientrecords->firstItem() ?? 0 }} to {{ $patientrecords->lastItem() ?? 0 }} of {{ $patientrecords->total() }} results
    </p>
    <div class="pagination-links">
        {{ $patientrecords->links('pagination::tailwind') }}
    </div>
</div>
