<?php

namespace App\Services;

use App\Exports\PatientRecordsExport;
use App\Models\Inventory;
use App\Models\Patientrecords;
use App\Models\User;
use App\Repositories\Interfaces\PatientRecordsRepositoryInterface;
use App\Support\SearchRelevance;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

class PatientRecordsAdminService
{
    private const MAX_INVENTORY_DEDUCTION_RETRIES = 3;

    public function __construct(
        protected PatientRecordsRepositoryInterface $patientRecordsRepository,
        protected BranchAccessService $branchAccessService
    ) {}

    public function showpatientrecords(Request $request)
    {
        $user = Auth::user();
        $accessibleBranchIds = $this->branchAccessService->accessibleBranchIds($user);
        $branches = $this->patientRecordsRepository->getAllBranches($accessibleBranchIds);
        $activeBranchIds = $branches->pluck('id')->map(fn ($id) => (int) $id)->all();
        $filters = $this->validatedFilters($request, $user, $activeBranchIds);

        $query = $this->buildFilteredPatientRecordsQuery($filters, $user)
            ->with(['dispensedMedications', 'barangay', 'branch'])
            ->orderByDesc('date_dispensed')
            ->orderByDesc('id');

        $patientrecords = $query->paginate(20)->withQueryString();

        if ($request->ajax()) {
            return view('admin.partials.patientrecords_table', compact('patientrecords'))->render();
        }

        $products = $this->patientRecordsRepository->getActiveInventoriesWithProduct($accessibleBranchIds);
        $barangays = $this->patientRecordsRepository->getAllBarangays();
        $statsQuery = $this->buildFilteredPatientRecordsQuery($filters, $user);
        $totalPeopleServed = (clone $statsQuery)
            ->distinct('patientrecords.id')
            ->count('patientrecords.id');
        $totalProductsDispensed = (clone $statsQuery)
            ->leftJoin('dispensedmedications', 'dispensedmedications.patientrecord_id', '=', 'patientrecords.id')
            ->count('dispensedmedications.id');

        return view('admin.patientrecords', compact(
            'products',
            'barangays',
            'branches',
            'patientrecords',
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
            'date-dispensed' => 'required|date|before_or_equal:today',
            'medications' => 'required|array|min:1|max:50',
            'medications.*.name' => 'required|integer',
            'medications.*.quantity' => 'required|integer|min:1|max:9999',
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

        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            abort(403, 'Authentication required.');
        }

        $branchId = $this->branchAccessService->resolveBranchFilter($user, null);

        Log::info('patient-records.adddispensation.started', [
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'medications_count' => count($validated['medications']),
        ]);

        try {
            /** @var Patientrecords $newRecord */
            $newRecord = DB::transaction(function () use ($validated, $user, $branchId): Patientrecords {
                $inventories = $this->loadAndLockInventories($validated['medications'], $user);
                $this->validateMedicationInventory($inventories, $validated['medications']);

                $patientRecord = $this->patientRecordsRepository->createPatientRecord([
                    'patient_name' => trim($validated['patient-name']),
                    'barangay_id' => $this->safeIntCast($validated['barangay_id']),
                    'purok' => trim($validated['purok']),
                    'category' => $validated['category'],
                    'date_dispensed' => $validated['date-dispensed'],
                    'branch_id' => $branchId,
                ]);

                $this->patientRecordsRepository->createHistoryLog([
                    'action' => 'RECORD ADDED',
                    'description' => sprintf(
                        'Recorded medication dispensation for patient %s (Record #: %d) at %s.',
                        $patientRecord->patient_name,
                        $patientRecord->id,
                        $user->branch?->name ?? 'Branch ID '.$user->branch_id
                    ),
                    'user_id' => $user->id,
                    'user_name' => $user->name ?? 'System',
                    'metadata' => [
                        'patientrecord_id' => $patientRecord->id,
                        'branch_id' => $branchId,
                        'medication_count' => count($validated['medications']),
                    ],
                ]);

                foreach ($validated['medications'] as $medication) {
                    $inventoryId = $this->safeIntCast($medication['name']);
                    $quantity = $this->safeIntCast($medication['quantity']);

                    $this->processIndividualMedication(
                        $inventories->get($inventoryId),
                        $quantity,
                        $patientRecord,
                        $this->safeIntCast($validated['barangay_id']),
                        $user
                    );
                }

                return $patientRecord;
            }, self::MAX_INVENTORY_DEDUCTION_RETRIES);

            Log::info('patient-records.adddispensation.completed', [
                'user_id' => $user->id,
                'patient_record_id' => $newRecord->id,
                'branch_id' => $branchId,
            ]);

            return to_route('admin.patientrecords')->with('success', 'Dispensation recorded successfully.');
        } catch (ValidationException $exception) {
            $exception->errorBag = 'adddispensation';

            Log::notice('patient-records.adddispensation.validation_failed', [
                'user_id' => $user->id,
                'errors' => $exception->errors(),
            ]);

            throw $exception;
        } catch (ModelNotFoundException $exception) {
            Log::warning('patient-records.adddispensation.inventory_missing', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withErrors(['medications' => $exception->getMessage()], 'adddispensation')
                ->withInput();
        } catch (Throwable $exception) {
            Log::error('patient-records.adddispensation.failed', [
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return back()
                ->withErrors(['medications' => 'System error while processing the dispensation.'], 'adddispensation')
                ->withInput();
        }
    }

    /**
     * Load and lock all requested inventories inside the active transaction.
     *
     * @param  array<int, array{name:mixed, quantity:mixed}>  $medications
     * @return Collection<int, Inventory>
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    private function loadAndLockInventories(array $medications, User $user): Collection
    {
        $inventoryIds = collect($medications)
            ->pluck('name')
            ->map(fn (mixed $inventoryId): int => $this->safeIntCast($inventoryId))
            ->filter(fn (int $inventoryId): bool => $inventoryId > 0)
            ->unique()
            ->values();

        if ($inventoryIds->isEmpty()) {
            throw ValidationException::withMessages([
                'medications' => 'At least one valid medication inventory is required.',
            ])->errorBag('adddispensation');
        }

        $inventories = Inventory::query()
            ->with('product')
            ->active()
            ->whereIn('id', $inventoryIds->all())
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Inventory $inventory): int => (int) $inventory->id);

        if ($inventories->count() !== $inventoryIds->count()) {
            $missingIds = $inventoryIds
                ->diff($inventories->keys()->map(fn ($id) => (int) $id))
                ->values()
                ->all();

            throw new ModelNotFoundException(
                'Medicine inventory not found or archived: '.implode(', ', $missingIds)
            );
        }

        foreach ($inventories as $inventory) {
            $this->branchAccessService->authorizeBranchAccess(
                $user,
                $inventory->branch_id,
                'dispense inventory from another branch'
            );
        }

        return $inventories;
    }

    /**
     * Validate requested medication quantities against locked inventory rows.
     *
     * @param  Collection<int, Inventory>  $inventories
     * @param  array<int, array{name:mixed, quantity:mixed}>  $medications
     *
     * @throws ValidationException
     */
    private function validateMedicationInventory(Collection $inventories, array $medications): void
    {
        $validationErrors = [];

        foreach ($medications as $medication) {
            $inventoryId = $this->safeIntCast($medication['name']);
            $inventory = $inventories->get($inventoryId);

            if (! $inventory) {
                $validationErrors[] = "Medicine inventory {$inventoryId} was not found.";

                continue;
            }

            $requestedQty = $this->safeFloatCast($medication['quantity']);
            $onHandQty = $this->safeIntCast($inventory->onhand_qty ?? $inventory->quantity ?? 0);
            $holdQty = $this->safeIntCast($inventory->hold_qty ?? 0);
            $availableQty = $onHandQty - $holdQty;
            $productName = $inventory->product->generic_name ?? 'Unknown medicine';

            if ($requestedQty <= 0) {
                $validationErrors[] = "{$productName}: Quantity must be positive.";
            }

            if ($requestedQty > 9999) {
                $validationErrors[] = "{$productName}: Quantity exceeds the maximum allowed.";
            }

            if ($availableQty < 0) {
                $validationErrors[] = "{$productName}: Inventory state is corrupted. Available stock is negative.";
            }

            if ($requestedQty > $availableQty) {
                $validationErrors[] = sprintf(
                    '%s: Insufficient stock. Requested %d, available %d.',
                    $productName,
                    (int) $requestedQty,
                    $availableQty
                );
            }
        }

        if ($validationErrors !== []) {
            throw ValidationException::withMessages([
                'medications' => implode(' ', $validationErrors),
            ])->errorBag('adddispensation');
        }
    }

    /**
     * Persist the stock deduction, movement log, and dispensed medication row.
     *
     * @throws RuntimeException
     */
    private function processIndividualMedication(
        ?Inventory $inventory,
        int $quantity,
        Patientrecords $patientRecord,
        int $barangayId,
        User $user
    ): void {
        if (! $inventory) {
            throw new RuntimeException('Medication inventory could not be loaded for processing.');
        }

        $quantityBefore = $this->safeIntCast($inventory->onhand_qty ?? $inventory->quantity ?? 0);
        $quantityAfter = $quantityBefore - $quantity;

        if ($quantityAfter < 0) {
            throw new RuntimeException(
                "Cannot deduct {$quantity} units from inventory #{$inventory->id} with {$quantityBefore} available."
            );
        }

        $inventory->onhand_qty = $quantityAfter;
        $inventory->quantity = $quantityAfter;
        $inventory->save();

        $this->patientRecordsRepository->createProductMovement([
            'product_id' => $inventory->product_id,
            'inventory_id' => $inventory->id,
            'user_id' => $user->id,
            'type' => 'OUT',
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'description' => sprintf(
                'Dispensed to Patient: %s (Record: #%d)',
                $patientRecord->patient_name,
                $patientRecord->id
            ),
        ]);

        $this->patientRecordsRepository->createDispensedMedication([
            'patientrecord_id' => $patientRecord->id,
            'barangay_id' => $barangayId,
            'batch_number' => trim((string) ($inventory->batch_number ?? 'N/A')),
            'generic_name' => trim((string) ($inventory->product->generic_name ?? 'N/A')),
            'brand_name' => trim((string) ($inventory->product->brand_name ?? 'N/A')),
            'strength' => trim((string) ($inventory->product->strength ?? 'N/A')),
            'form' => trim((string) ($inventory->product->form ?? 'N/A')),
            'quantity' => $quantity,
        ]);

        Log::info('patient-records.adddispensation.medication_processed', [
            'patient_record_id' => $patientRecord->id,
            'inventory_id' => $inventory->id,
            'product_id' => $inventory->product_id,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
        ]);
    }

    /**
     * Cast a numeric value to int while rejecting non-numeric input.
     *
     * @throws RuntimeException
     */
    private function safeIntCast(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        if (! is_numeric($value)) {
            throw new RuntimeException('Cannot convert a non-numeric value to int.');
        }

        $floatValue = (float) $value;
        $intValue = (int) $floatValue;

        if (fmod($floatValue, 1.0) !== 0.0) {
            Log::warning('patient-records.safe_int_cast_precision_loss', [
                'original' => $value,
                'cast_result' => $intValue,
            ]);
        }

        return $intValue;
    }

    /**
     * Cast a numeric value to float while rejecting non-numeric input.
     *
     * @throws RuntimeException
     */
    private function safeFloatCast(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        if (! is_numeric($value)) {
            throw new RuntimeException('Cannot convert a non-numeric value to float.');
        }

        return (float) $value;
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
        $this->branchAccessService->authorizeBranchAccess($user, $record->branch_id, 'update patient records from another branch');

        // capture old values before updating
        $old = $record->only(['patient_name', 'barangay_id', 'purok', 'category', 'date_dispensed']);
        $old['barangay_name'] = $record->barangay->barangay_name;

        // Update the patient record
        $record->update([
            'patient_name' => $validated['patient-name'],
            'barangay_id' => $validated['barangay_id'],
            'purok' => $validated['purok'],
            'category' => $validated['category'],
            'date_dispensed' => $validated['date-dispensed'],
        ]);

        // HISTORY LOG: UPDATE
        $oldDate = Carbon::parse($old['date_dispensed'])->format('F d, Y');
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
        $canAccessAllBranches = $this->branchAccessService->canAccessAllBranches($user);
        $branches = $this->patientRecordsRepository->getAllBranches($this->branchAccessService->accessibleBranchIds($user));
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
                'branch' => ! $canAccessAllBranches
                    ? ($user->branch->name ?? ('Branch ID '.$user->branch_id))
                    : ($filters['branch_filter'] === 'all'
                        ? 'All Branches'
                        : ($branches->firstWhere('id', (int) $filters['branch_filter'])->name ?? 'Unknown Branch')),
                'barangay' => $filters['barangay_id']
                    ? ($barangays->firstWhere('id', (int) $filters['barangay_id'])->barangay_name ?? 'Unknown Barangay')
                    : 'All Barangays',
            ],
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('patient_records_'.Carbon::now()->format('Ymd_His').'.pdf');
    }

    public function exportExcel(Request $request)
    {
        $user = Auth::user();
        $activeBranchIds = $this->patientRecordsRepository->getAllBranches($this->branchAccessService->accessibleBranchIds($user))
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
            'patient_records_'.Carbon::now()->format('Ymd_His').'.xlsx'
        );
    }

    public function buildFilteredPatientRecordsQuery(array $filters, User $user): Builder
    {
        $query = $this->patientRecordsRepository->patientRecordsQuery()
            ->select('patientrecords.*');
        $canAccessAllBranches = $this->branchAccessService->canAccessAllBranches($user);
        $search = SearchRelevance::normalize($filters['search'] ?? '');
        $searchTokens = SearchRelevance::tokens($search);

        $query
            ->when($canAccessAllBranches && ($filters['branch_filter'] ?? 'all') !== 'all', function (Builder $q) use ($filters) {
                $q->where('patientrecords.branch_id', (int) $filters['branch_filter']);
            })
            ->when(! $canAccessAllBranches, function (Builder $q) use ($user) {
                $q->where('patientrecords.branch_id', (int) $user->branch_id);
            })
            ->when($search !== '', function (Builder $q) use ($search, $searchTokens) {
                $q->leftJoin('barangays', 'barangays.id', '=', 'patientrecords.barangay_id')
                    ->leftJoin('branches', 'branches.id', '=', 'patientrecords.branch_id');

                $q->where(function (Builder $searchQuery) use ($search, $searchTokens) {
                    $containsPattern = SearchRelevance::containsPattern($search);

                    $searchQuery
                        ->whereRaw(SearchRelevance::lower('patientrecords.patient_name')." LIKE ? ESCAPE '!'", [$containsPattern])
                        ->orWhereRaw(SearchRelevance::lower('patientrecords.purok')." LIKE ? ESCAPE '!'", [$containsPattern])
                        ->orWhereRaw(SearchRelevance::lower('patientrecords.category')." LIKE ? ESCAPE '!'", [$containsPattern])
                        ->orWhereRaw(SearchRelevance::lower('barangays.barangay_name')." LIKE ? ESCAPE '!'", [$containsPattern])
                        ->orWhereRaw(SearchRelevance::lower('branches.name')." LIKE ? ESCAPE '!'", [$containsPattern]);

                    if (count($searchTokens) > 1) {
                        $searchQuery->orWhere(function (Builder $tokenQuery) use ($searchTokens) {
                            foreach ($searchTokens as $token) {
                                $tokenQuery->whereRaw(SearchRelevance::lower('patientrecords.patient_name')." LIKE ? ESCAPE '!'", [
                                    SearchRelevance::containsPattern($token),
                                ]);
                            }
                        });
                    }

                    if (ctype_digit($search)) {
                        $searchQuery->orWhere('patientrecords.id', (int) $search);
                    }
                });

                $weights = config('query_relevance.patient_records');
                $relevance = (new SearchRelevance)
                    ->exact(SearchRelevance::lower('patientrecords.patient_name'), $search, $weights['patient_name_exact'])
                    ->prefix(SearchRelevance::lower('patientrecords.patient_name'), $search, $weights['patient_name_prefix'])
                    ->contains(SearchRelevance::lower('patientrecords.patient_name'), $search, $weights['patient_name_contains'])
                    ->tokenContains(SearchRelevance::lower('patientrecords.patient_name'), $searchTokens, $weights['patient_name_token'])
                    ->exact(SearchRelevance::lower('barangays.barangay_name'), $search, $weights['barangay_exact'])
                    ->prefix(SearchRelevance::lower('barangays.barangay_name'), $search, $weights['barangay_prefix'])
                    ->contains(SearchRelevance::lower('barangays.barangay_name'), $search, $weights['barangay_contains'])
                    ->exact(SearchRelevance::lower('branches.name'), $search, $weights['branch_exact'])
                    ->prefix(SearchRelevance::lower('branches.name'), $search, $weights['branch_prefix'])
                    ->contains(SearchRelevance::lower('branches.name'), $search, $weights['branch_contains'])
                    ->exact(SearchRelevance::lower('patientrecords.purok'), $search, $weights['purok_exact'])
                    ->prefix(SearchRelevance::lower('patientrecords.purok'), $search, $weights['purok_prefix'])
                    ->contains(SearchRelevance::lower('patientrecords.purok'), $search, $weights['purok_contains'])
                    ->exact(SearchRelevance::lower('patientrecords.category'), $search, $weights['category_exact']);

                if (ctype_digit($search)) {
                    $relevance->custom('patientrecords.id = ?', [(int) $search], $weights['id_exact']);
                }

                $compiled = $relevance->compile();
                $q->selectRaw($compiled['sql'], $compiled['bindings'])
                    ->orderByDesc('relevance_score');
            })
            ->when(($filters['category'] ?? 'all') !== 'all', function (Builder $q) use ($filters) {
                $q->where('patientrecords.category', $filters['category']);
            })
            ->when(! empty($filters['barangay_id']), function (Builder $q) use ($filters) {
                $q->where('patientrecords.barangay_id', (int) $filters['barangay_id']);
            })
            ->when(! empty($filters['from_date']), function (Builder $q) use ($filters) {
                $q->whereDate('patientrecords.date_dispensed', '>=', $filters['from_date']);
            })
            ->when(! empty($filters['to_date']), function (Builder $q) use ($filters) {
                $q->whereDate('patientrecords.date_dispensed', '<=', $filters['to_date']);
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
        if ($this->branchAccessService->canAccessAllBranches($user)) {
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
