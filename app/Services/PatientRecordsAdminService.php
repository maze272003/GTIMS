<?php

namespace App\Services;

use App\Exports\PatientRecordsExport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Repositories\Interfaces\PatientRecordsRepositoryInterface;

class PatientRecordsAdminService
{
    public function __construct(
        protected PatientRecordsRepositoryInterface $patientRecordsRepository
    ) {
    }

    public function showpatientrecords(Request $request)
    {
        $user = Auth::user();
        $branches = $this->patientRecordsRepository->getAllBranches();
        $activeBranchIds = $branches->pluck('id')->map(fn ($id) => (int) $id)->all();
        $filters = $this->validatedFilters($request, $user, $activeBranchIds);

        // === 1. BUILD THE QUERY ===
        $query = $this->buildFilteredPatientRecordsQuery($filters, $user)
            ->with(['dispensedMedications', 'barangay', 'branch'])
            ->orderByDesc('date_dispensed')
            ->orderByDesc('id');

        // === 2. PAGINATION ===
        $patientrecords = $query->paginate(20)->withQueryString();

        // === 3. AJAX CHECK ===
        if ($request->ajax()) {
            return view('admin.partials.patientrecords_table', compact('patientrecords'))->render();
        }

        // === 4. LOAD FULL PAGE DATA ===
        $products = $this->patientRecordsRepository->getActiveInventoriesWithProduct();
        $barangays = $this->patientRecordsRepository->getAllBarangays();

        // Calculate Stats
        $patientrecordscard = $this->buildFilteredPatientRecordsQuery($filters, $user)
            ->with('dispensedMedications')
            ->get();

        $totalPeopleServed = $patientrecordscard->count();
        $totalProductsDispensed = $patientrecordscard->sum(function ($record) {
            return $record->dispensedMedications->count();
        });

        return view('admin.patientrecords', compact(
            'products',
            'barangays',
            'branches',
            'patientrecords',
            'patientrecordscard',
            'totalPeopleServed',
            'totalProductsDispensed',
            'filters'
        ));
    }

    public function adddispensation(Request $request) 
    {
        $validated = $request->validateWithBag('adddispensation', [
            'patient-name' => 'required|string|max:255',
            'barangay_id' => 'required|exists:barangays,id',
            'purok' => 'required|string|max:255',
            'category' => 'required|in:Adult,Child,Senior',
            'date-dispensed' => 'required|date',
            'medications' => 'required|array|min:1',
            'medications.*.name' => 'required|exists:inventories,id',
            'medications.*.quantity' => 'required|integer|min:1',
        ], [
            'patient-name.required' => 'Patient name is required.',
            'barangay_id.required' => 'Barangay is required.',
            'purok.required' => 'Purok is required.',
            'category.required' => 'Category is required.',
            'date-dispensed.required' => 'Date dispensed is required.',
            'medications.required' => 'At least one medication is required.',
            'medications.*.name.required' => 'Medicine selection is required.',
            'medications.*.quantity.required' => 'Quantity is required.',
        ]);

        $user = Auth::user(); 

        // Check inventory first
        foreach ($validated['medications'] as $med) {
            $inventory = $this->patientRecordsRepository->findInventoryWithProductOrFail((int) $med['name']);
            if ($inventory->quantity < $med['quantity']) {
                return back()->withErrors(['medications' => 'Insufficient quantity for ' . ($inventory->product->generic_name ?? 'medicine') . '. Available: ' . $inventory->quantity], 'adddispensation')->withInput();
            }
        }

        // Create PatientRecord
        $newRecord = $this->patientRecordsRepository->createPatientRecord([
            'patient_name' => $validated['patient-name'],
            'barangay_id' => $validated['barangay_id'],
            'purok' => $validated['purok'],
            'category' => $validated['category'],
            'date_dispensed' => $validated['date-dispensed'],
            'branch_id' => $user->branch_id,
        ]);

        // === HISTORY LOG ===
        $this->patientRecordsRepository->createHistoryLog([
            'action' => 'RECORD ADDED',
            'description' => "Recorded medication dispensation for patient {$newRecord->patient_name} (Record #: {$newRecord->id}) at " . ($user->branch->name ?? 'Branch ID ' . $user->branch_id) . ".",
            'user_id' => $user->id,
            'user_name' => $user->name ?? 'System',
            'metadata' => [
                'patientrecord_id' => $newRecord->id,
                'branch_id' => $user->branch_id
            ],
        ]);

        // Create dispensed medications and deduct inventory
        foreach ($validated['medications'] as $med) {
            $inventory = $this->patientRecordsRepository->findInventoryWithProductOrFail((int) $med['name']);
            
            
            $quantity_before = $inventory->quantity;
            $quantity_to_deduct = $med['quantity'];
            $quantity_after = $quantity_before - $quantity_to_deduct;

            // Deduct inventory
            $inventory->quantity = $quantity_after;
            $inventory->save();

            // Log Product Movement
            $this->patientRecordsRepository->createProductMovement([
                'product_id'      => $inventory->product_id,
                'inventory_id'    => $inventory->id,
                'user_id'         => $user->id,
                'type'            => 'OUT',
                'quantity'        => $quantity_to_deduct,
                'quantity_before' => $quantity_before,
                'quantity_after'  => $quantity_after,
                'description'     => "Dispensed to Patient: {$newRecord->patient_name} (Record: #{$newRecord->id})",
            ]);

            $this->patientRecordsRepository->createDispensedMedication([
                'patientrecord_id' => $newRecord->id,
                'barangay_id' => $validated['barangay_id'],
                'batch_number' => $inventory->batch_number ?? 'N/A',
                'generic_name' => $inventory->product->generic_name ?? 'N/A',
                'brand_name' => $inventory->product->brand_name ?? 'N/A',
                'strength' => $inventory->product->strength ?? 'N/A',
                'form' => $inventory->product->form ?? 'N/A',
                'quantity' => $med['quantity'],
            ]);
        }

        return to_route('admin.patientrecords')->with('success', 'Dispensation recorded successfully.');
    }

    public function updatePatientRecord(Request $request)
    {
        $id = $request->input('id');

        $validated = $request->validateWithBag('editdispensation', [
            'patient-name' => 'required|string|max:255',
            'barangay_id' => 'required|exists:barangays,id',
            'purok' => 'required|string|max:255',
            'category' => 'required|in:Adult,Child,Senior',
            'date-dispensed' => 'required|date',
        ], [
            'patient-name.required' => 'Patient name is required.',
            'barangay_id.required' => 'Barangay is required.',
            'purok.required' => 'Purok is required.',
            'category.required' => 'Category is required.',
            'date-dispensed.required' => 'Date dispensed is required.',
        ]);

        $record = $this->patientRecordsRepository->findPatientRecordWithBarangayOrFail((int) $id);
        $user = Auth::user();

        // SECURITY CHECK
        if (!$user->hasPermission('patients.manage') && $record->branch_id != $user->branch_id) {
            return back()->with('error', 'Unauthorized action.');
        }

        // capture old values before updating
        $old = $record->only(['patient_name', 'barangay_id', 'purok', 'category', 'date_dispensed']);
        $old["barangay_name"] = $record->barangay->barangay_name;

        // Update the patient record
        $record->update([
            'patient_name' => $validated['patient-name'],
            'barangay_id' => $validated['barangay_id'],
            'purok' => $validated['purok'],
            'category' => $validated['category'],
            'date_dispensed' => $validated['date-dispensed'],
        ]);

        // HISTORY LOG: UPDATE
        $oldDate = Carbon::parse($old["date_dispensed"])->format('F d, Y');
        $newDate = Carbon::parse($record->date_dispensed)->format('F d, Y');    
        $time = Carbon::parse($record->created_at)->format('h:i A');

        $this->patientRecordsRepository->createHistoryLog([
            'action' => 'RECORD UPDATED',
            'description' => "Updated patient record #{$record->id} for {$record->patient_name}. 
            CHANGES: 
            - Patient Name: {$old['patient_name']} to {$record->patient_name}. 
            - Baragay: {$old['barangay_name']} to {$record->barangay->barangay_name}. 
            - Purok: {$old['purok']} to {$record->purok}. 
            - Category: {$old['category']} to {$record->category}. 
            - Date Dispensed: {$oldDate} ({$time}) to {$newDate} ({$time}).",
            'user_id' => $user->id,
            'user_name' => $user->name ?? 'System',
            'metadata' => [
                'patientrecord_id' => $record->id,
            ],
        ]);

        if ($old['barangay_id'] != $validated['barangay_id']) {
            $this->patientRecordsRepository->updateDispensedMedicationsBarangay((int) $id, (int) $validated['barangay_id']);
        }

        return to_route('admin.patientrecords')->with('success', 'Dispensation record updated successfully.');
    }

    public function exportPdf(Request $request)
    {
        $user = Auth::user();
        $canManagePatients = $user->hasPermission('patients.manage');
        $branches = $this->patientRecordsRepository->getAllBranches();
        $barangays = $this->patientRecordsRepository->getAllBarangays();
        $activeBranchIds = $branches->pluck('id')->map(fn ($id) => (int) $id)->all();
        $filters = $this->validatedFilters($request, $user, $activeBranchIds);

        // 1. REUSE FILTERS
        $query = $this->buildFilteredPatientRecordsQuery($filters, $user)
            ->with(['dispensedMedications', 'barangay', 'branch'])
            ->orderByDesc('date_dispensed')
            ->orderByDesc('id');

        // 2. GET DATA
        $records = $query->get();

        // 3. GENERATE PDF
        $pdf = Pdf::loadView('admin.pdf.patientrecords_pdf', [
            'patientrecords' => $records,
            'generated_by' => $user->name,
            'date' => Carbon::now()->format('F d, Y'),
            'filters' => $filters,
            'filter_labels' => [
                'branch' => !$canManagePatients
                    ? ($user->branch->name ?? ('Branch ID ' . $user->branch_id))
                    : ($filters['branch_filter'] === 'all'
                        ? 'All Branches'
                        : ($branches->firstWhere('id', (int) $filters['branch_filter'])->name ?? 'Unknown Branch')),
                'barangay' => $filters['barangay_id']
                    ? ($barangays->firstWhere('id', (int) $filters['barangay_id'])->barangay_name ?? 'Unknown Barangay')
                    : 'All Barangays',
            ],
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('patient_records_' . Carbon::now()->format('Ymd_His') . '.pdf');
    }
    public function exportExcel(Request $request)
    {
        $user = Auth::user();
        $activeBranchIds = $this->patientRecordsRepository->getAllBranches()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $filters = $this->validatedFilters($request, $user, $activeBranchIds);

        $query = $this->buildFilteredPatientRecordsQuery($filters, $user)
            ->with(['dispensedMedications', 'barangay', 'branch'])
            ->orderByDesc('date_dispensed')
            ->orderByDesc('id');

        return Excel::download(
            new PatientRecordsExport($query, $user, $filters),
            'patient_records_' . Carbon::now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function buildFilteredPatientRecordsQuery(array $filters, User $user): Builder
    {
        $query = $this->patientRecordsRepository->patientRecordsQuery();
        $canManagePatients = $user->hasPermission('patients.manage');
        $search = (string) ($filters['search'] ?? '');
        $searchTokens = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $query
            ->when($canManagePatients && ($filters['branch_filter'] ?? 'all') !== 'all', function (Builder $q) use ($filters) {
                $q->where('branch_id', (int) $filters['branch_filter']);
            })
            ->when(!$canManagePatients, function (Builder $q) use ($user) {
                $q->where('branch_id', (int) $user->branch_id);
            })
            ->when($search !== '', function (Builder $q) use ($search, $searchTokens) {
                $q->where(function (Builder $searchQuery) use ($search, $searchTokens) {
                    $searchQuery->where('patient_name', 'like', "%{$search}%")
                        ->orWhere('purok', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('barangay', function (Builder $barangayQuery) use ($search) {
                            $barangayQuery->where('barangay_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('branch', function (Builder $branchQuery) use ($search) {
                            $branchQuery->where('name', 'like', "%{$search}%");
                        });

                    if (count($searchTokens) > 1) {
                        $searchQuery->orWhere(function (Builder $tokenQuery) use ($searchTokens) {
                            foreach ($searchTokens as $token) {
                                $tokenQuery->where('patient_name', 'like', "%{$token}%");
                            }
                        });
                    }

                    if (ctype_digit($search)) {
                        $searchQuery->orWhere('id', (int) $search);
                    }
                });
            })
            ->when(($filters['category'] ?? 'all') !== 'all', function (Builder $q) use ($filters) {
                $q->where('category', $filters['category']);
            })
            ->when(!empty($filters['barangay_id']), function (Builder $q) use ($filters) {
                $q->where('barangay_id', (int) $filters['barangay_id']);
            })
            ->when(!empty($filters['from_date']), function (Builder $q) use ($filters) {
                $q->whereDate('date_dispensed', '>=', $filters['from_date']);
            })
            ->when(!empty($filters['to_date']), function (Builder $q) use ($filters) {
                $q->whereDate('date_dispensed', '<=', $filters['to_date']);
            });

        return $query;
    }

    private function validatedFilters(Request $request, User $user, array $activeBranchIds): array
    {
        $validated = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:255'],
            'branch_filter' => ['nullable', 'string'],
            'category' => ['nullable', 'string', Rule::in(['all', 'Adult', 'Child', 'Senior'])],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ])->validate();

        $branchFilter = 'all';
        if ($user->hasPermission('patients.manage')) {
            $rawBranchFilter = (string) ($validated['branch_filter'] ?? 'all');
            if ($rawBranchFilter !== '' && $rawBranchFilter !== 'all') {
                $branchId = (int) $rawBranchFilter;
                $branchFilter = in_array($branchId, $activeBranchIds, true) ? $branchId : 'all';
            }
        }

        $search = trim((string) ($validated['search'] ?? ''));

        return [
            'search' => preg_replace('/\s+/', ' ', $search) ?? '',
            'branch_filter' => $branchFilter,
            'category' => (string) ($validated['category'] ?? 'all'),
            'barangay_id' => isset($validated['barangay_id']) ? (int) $validated['barangay_id'] : null,
            'from_date' => $validated['from_date'] ?? null,
            'to_date' => $validated['to_date'] ?? null,
        ];
    }
}
