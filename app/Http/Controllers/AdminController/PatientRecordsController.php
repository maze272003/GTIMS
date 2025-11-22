<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Patientrecords;
use App\Models\Dispensedmedication;
use App\Models\ProductMovement;
use App\Models\Barangay;
use App\Models\Branch; // Don't forget to import Branch
use Illuminate\Support\Facades\Auth;
use App\Models\HistoryLog;
use Carbon\Carbon;

class PatientRecordsController extends Controller
{
    public function showpatientrecords(Request $request)
    {
        $user = Auth::user();
        $products = Inventory::with('product')->where('is_archived', 2)->latest()->get();
        $barangays = Barangay::all();
        $branches = Branch::all();

        // Base query
        $query = Patientrecords::with(['dispensedMedications', 'barangay', 'branch']);

        // === BRANCH FILTERING (Admin vs Regular User) ===
        if (in_array($user->user_level_id, [1, 2])) {
            // Admin: optional branch filter
            if ($request->filled('branch_filter') && $request->branch_filter !== 'all') {
                $query->where('branch_id', $request->branch_filter);
            }
        } else {
            // Encoder/Doctor: only own branch
            $query->where('branch_id', $user->branch_id);
        }

        // === APPLY OTHER FILTERS ===
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('category') && $request->category !== '') {
            $query->where('category', $request->category);
        }

        if ($request->filled('barangay_id') && $request->barangay_id !== '') {
            $query->where('barangay_id', $request->barangay_id);
        }

        // Paginated results
        $patientrecords = $query->latest()->paginate(20)->withQueryString(); // Important: preserves filters in pagination

        // === Stats Calculation (Same filters applied) ===
        $statsQuery = Patientrecords::query();

        if (in_array($user->user_level_id, [1, 2])) {
            if ($request->filled('branch_filter') && $request->branch_filter !== 'all') {
                $statsQuery->where('branch_id', $request->branch_filter);
            }
        } else {
            $statsQuery->where('branch_id', $user->branch_id);
        }

        if ($request->filled('from_date')) {
            $statsQuery->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $statsQuery->whereDate('created_at', '<=', $request->to_date);
        }
        if ($request->filled('category') && $request->category !== '') {
            $statsQuery->where('category', $request->category);
        }
        if ($request->filled('barangay_id') && $request->barangay_id !== '') {
            $statsQuery->where('barangay_id', $request->barangay_id);
        }

        $patientrecordscard = $statsQuery->with('dispensedMedications')->get();

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
            'totalProductsDispensed'
        ))->with('currentFilter', $request->branch_filter ?? 'all');
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
            $inventory = Inventory::findOrFail($med['name']);
            if ($inventory->quantity < $med['quantity']) {
                return back()->withErrors(['medications' => 'Insufficient quantity for ' . ($inventory->product->generic_name ?? 'medicine') . '. Available: ' . $inventory->quantity], 'adddispensation')->withInput();
            }
        }

        // Create PatientRecord
        // IMPORTANT: We explicitly set the branch_id based on the logged-in user
        $newRecord = Patientrecords::create([
            'patient_name' => $validated['patient-name'],
            'barangay_id' => $validated['barangay_id'],
            'purok' => $validated['purok'],
            'category' => $validated['category'],
            'date_dispensed' => $validated['date-dispensed'],
            'branch_id' => $user->branch_id, // <--- AUTO-ASSIGN USER'S BRANCH
        ]);

        // === HISTORY LOG ===
        HistoryLog::create([
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
            $inventory = Inventory::findOrFail($med['name']);
            
            $quantity_before = $inventory->quantity;
            $quantity_to_deduct = $med['quantity'];
            $quantity_after = $quantity_before - $quantity_to_deduct;

            // Deduct inventory
            $inventory->quantity = $quantity_after;
            $inventory->save();

            // Log Product Movement
            ProductMovement::create([
                'product_id'      => $inventory->product_id,
                'inventory_id'    => $inventory->id,
                'user_id'         => $user->id,
                'type'            => 'OUT',
                'quantity'        => $quantity_to_deduct,
                'quantity_before' => $quantity_before,
                'quantity_after'  => $quantity_after,
                'description'     => "Dispensed to Patient: {$newRecord->patient_name} (Record: #{$newRecord->id})",
            ]);

            $dispensedMed = new Dispensedmedication;
            $dispensedMed->patientrecord_id = $newRecord->id;
            $dispensedMed->barangay_id = $validated['barangay_id'];
            $dispensedMed->batch_number = $inventory->batch_number ?? 'N/A';
            $dispensedMed->generic_name = $inventory->product->generic_name ?? 'N/A';
            $dispensedMed->brand_name = $inventory->product->brand_name ?? 'N/A';
            $dispensedMed->strength = $inventory->product->strength ?? 'N/A';
            $dispensedMed->form = $inventory->product->form ?? 'N/A';
            $dispensedMed->quantity = $med['quantity'];
            $dispensedMed->save();
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

        $record = Patientrecords::with('barangay')->findOrFail($id);
        $user = Auth::user();

        // SECURITY CHECK: Ensure Encoders can't edit records from other branches via ID manipulation
        if (!in_array($user->user_level_id, [1, 2]) && $record->branch_id != $user->branch_id) {
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
            // Note: We usually don't allow changing the branch_id on edit unless specifically required
        ]);

        // HISTORY LOG: UPDATE
        $oldDate = Carbon::parse($old["date_dispensed"])->format('F d, Y');
        $newDate = Carbon::parse($record->date_dispensed)->format('F d, Y');    
        $time = Carbon::parse($record->created_at)->format('h:i A');

        HistoryLog::create([
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

        // update barangay_id in related dispensed medications if changed
        if ($record->barangay_id != $validated['barangay_id']) {
            Dispensedmedication::where('patientrecord_id', $id)->update(['barangay_id' => $validated['barangay_id']]);
        }

        return to_route('admin.patientrecords')->with('success', 'Dispensation updated successfully.');
    }
}