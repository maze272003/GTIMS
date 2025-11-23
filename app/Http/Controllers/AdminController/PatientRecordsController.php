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
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Str;
class PatientRecordsController extends Controller
{
    public function showpatientrecords(Request $request)
    {
        // 1. Get Products (Inventory) - You might want to filter this by branch too in the future, 
        // but for now we keep it as is to show available medicines.
        $products = Inventory::with('product')->where('is_archived', 2)->latest()->get();
        
        $barangays = Barangay::all();
        $branches = Branch::all(); // Get all branches for the Admin dropdown

        $user = Auth::user();

        // 2. Initialize the Query
        $query = Patientrecords::with(['dispensedMedications', 'barangay', 'branch']);

        // 3. Apply Authorization/Filtering Logic
        if (in_array($user->user_level_id, [1, 2])) {
            // === ADMIN (Level 1 & 2) ===
            // Admin can see everything, but if they selected a filter, apply it.
            if ($request->has('branch_filter') && $request->branch_filter != 'all') {
                $query->where('branch_id', $request->branch_filter);
            }
        } else {
            // === ENCODER / DOCTOR (Level 3 & 4) ===
            // Can ONLY see records from their own branch
            $query->where('branch_id', $user->branch_id);
        }

        // 4. Fetch Paginated Results
        $patientrecords = $query->latest()->paginate(20);
        if ($request->ajax()) {
        return view('admin.partials.patientrecords_table', compact('patientrecords'))->render();
         }
         $cardQuery = Patientrecords::with(['dispensedMedications']);
            if (in_array($user->user_level_id, [1, 2])) {
                if ($request->has('branch_filter') && $request->branch_filter != 'all') {
                    $cardQuery->where('branch_id', $request->branch_filter);
                }
            } else {
                $cardQuery->where('branch_id', $user->branch_id);
            }
        // 5. Calculate Stats (Using the same filter logic for accuracy)
        $cardQuery = Patientrecords::with(['dispensedMedications']);
        
        if (in_array($user->user_level_id, [1, 2])) {
            if ($request->has('branch_filter') && $request->branch_filter != 'all') {
                $cardQuery->where('branch_id', $request->branch_filter);
            }
        } else {
            $cardQuery->where('branch_id', $user->branch_id);
        }
        
        $patientrecordscard = $cardQuery->get();

        $totalPeopleServed = $patientrecordscard->count();
        $totalProductsDispensed = $patientrecordscard->sum(function ($patientrecord) {
            return $patientrecord->dispensedMedications->count();
        });

        return view('admin.patientrecords', [
            'products' => $products,
            'barangays' => $barangays,
            'patientrecords' => $patientrecords,
            'totalPeopleServed' => $totalPeopleServed,
            'totalProductsDispensed' => $totalProductsDispensed,
            'patientrecordscard' => $patientrecordscard,
            'branches' => $branches, // Pass branches to view
            'currentFilter' => $request->branch_filter ?? 'all' // Pass current filter selection
        ]);
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
            'signature' => 'required',
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
        $signaturePath = null; // Initialize variable

        if ($request->has('signature')) {
            // 1. Get base64 string
            $image_64 = $request->signature; 
            
            // 2. Separate the extension from the data
            // Minsan walang extension sa string, kaya safe na mag default sa png kung sakali, 
            // pero usually gumagana yung explode method.
            if (strpos($image_64, ';') !== false) {
                $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   
            } else {
                $extension = 'png';
            }

            $replace = substr($image_64, 0, strpos($image_64, ',')+1); 
            
            // 3. Decode the image
            $image = str_replace($replace, '', $image_64); 
            $image = str_replace(' ', '+', $image); 
            
            // 4. Generate unique name
            $imageName = 'signature_' . time() . '_' . Str::random(10) . '.' . $extension;
            
            // 5. Store the file in storage/app/public/signatures
            Storage::disk('public')->put('signatures/' . $imageName, base64_decode($image));
            
            // 6. Set path to save in DB
            $signaturePath = 'signatures/' . $imageName;
        }
        // Create PatientRecord
        // IMPORTANT: We explicitly set the branch_id based on the logged-in user
        $newRecord = Patientrecords::create([
            'patient_name' => $validated['patient-name'],
            'barangay_id' => $validated['barangay_id'],
            'purok' => $validated['purok'],
            'category' => $validated['category'],
            'date_dispensed' => $validated['date-dispensed'],
            'branch_id' => $user->branch_id,
             'signature_path' => $signaturePath,
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
        $signaturePath = null;
        if ($request->has('signature')) {
            // 1. Get base64 string
            $image_64 = $request->signature; 
            
            // 2. Separate the extension from the data
            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   
            $replace = substr($image_64, 0, strpos($image_64, ',')+1); 
            
            // 3. Decode the image
            $image = str_replace($replace, '', $image_64); 
            $image = str_replace(' ', '+', $image); 
            
            // 4. Generate unique name
            $imageName = 'signature_' . time() . '_' . Str::random(10) . '.' . $extension;
            
            // 5. Store the file in storage/app/public/signatures
            Storage::disk('public')->put('signatures/' . $imageName, base64_decode($image));
            
            // 6. Set path to save in DB
            $signaturePath = 'signatures/' . $imageName;
        }
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