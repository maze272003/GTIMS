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
use App\Models\Branch;
use Illuminate\Support\Facades\Auth;
use App\Models\HistoryLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PatientRecordsExport;

class PatientRecordsController extends Controller
{
    public function showpatientrecords(Request $request)
    {
        $user = Auth::user();

        // === 1. BUILD THE QUERY FOR THE TABLE (Paginated) ===
        $query = Patientrecords::with(['dispensedMedications', 'barangay', 'branch']);

        // --- Branch Filtering ---
        if (in_array($user->user_level_id, [1, 2])) {
            if ($request->filled('branch_filter') && $request->branch_filter !== 'all') {
                $query->where('branch_id', $request->branch_filter);
            }
        } else {
            $query->where('branch_id', $user->branch_id);
        }

        // 4. Fetch Paginated Results
        $patientrecords = $query->latest()->paginate(20);
        
        if ($request->ajax()) {
            return view('admin.partials.patientrecords_table', compact('patientrecords'))->render();
        }

        // === 5. BUILD THE QUERY FOR STATS (Totals) ===
        $statsQuery = Patientrecords::with(['dispensedMedications']);
        
        // Apply Filters to Stats Query
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

        // Get the results for calculation
        $patientrecordscard = $statsQuery->get();

        $totalPeopleServed = $patientrecordscard->count();
        $totalProductsDispensed = $patientrecordscard->sum(function ($record) {
            return $record->dispensedMedications->count();
        });

        // Fetch Dropdown Data
        $products = Inventory::with('product')->where('quantity', '>', 0)->get(); 
        $barangays = Barangay::all();
        $branches = Branch::all();

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
        
        $signaturePath = null; 

        // === FIXED: Full Logic for Image Decoding ===
        if ($request->has('signature')) {
            $image_64 = $request->signature; 
            
            // Get extension
            if (strpos($image_64, ';') !== false) {
                $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   
            } else {
                $extension = 'png';
            }

            $replace = substr($image_64, 0, strpos($image_64, ',')+1); 
            
            // Decode image
            $image = str_replace($replace, '', $image_64); 
            $image = str_replace(' ', '+', $image); 
            
            // Generate Name
            $imageName = 'signature_' . time() . '_' . Str::random(10) . '.' . $extension;
            
            // Store in LOCAL (Private) disk
            Storage::disk('local')->put('signatures/' . $imageName, base64_decode($image));
            
            // Save filename only
            $signaturePath = $imageName; 
        }

        // Create PatientRecord
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
        
        // === FIXED: Full Logic for Image Decoding ===
        if ($request->has('signature')) {
            $image_64 = $request->signature; 
            
            if (strpos($image_64, ';') !== false) {
                $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   
            } else {
                $extension = 'png';
            }

            $replace = substr($image_64, 0, strpos($image_64, ',')+1); 
            $image = str_replace($replace, '', $image_64); 
            $image = str_replace(' ', '+', $image); 
            
            $imageName = 'signature_' . time() . '_' . Str::random(10) . '.' . $extension;
            
            // Store in LOCAL (Private) disk
            Storage::disk('local')->put('signatures/' . $imageName, base64_decode($image));
            $signaturePath = $imageName;
        }

        // SECURITY CHECK
        if (!in_array($user->user_level_id, [1, 2]) && $record->branch_id != $user->branch_id) {
            return back()->with('error', 'Unauthorized action.');
        }

        // capture old values
        $old = $record->only(['patient_name', 'barangay_id', 'purok', 'category', 'date_dispensed']);
        $old["barangay_name"] = $record->barangay->barangay_name;

        // Update the patient record
        $updateData = [
            'patient_name' => $validated['patient-name'],
            'barangay_id' => $validated['barangay_id'],
            'purok' => $validated['purok'],
            'category' => $validated['category'],
            'date_dispensed' => $validated['date-dispensed'],
        ];

        // Only update signature if a new one was provided
        if ($signaturePath) {
            $updateData['signature_path'] = $signaturePath;
        }

        $record->update($updateData);

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

        if ($record->barangay_id != $validated['barangay_id']) {
            Dispensedmedication::where('patientrecord_id', $id)->update(['barangay_id' => $validated['barangay_id']]);
        }

        return to_route('admin.patientrecords')->with('success', 'Dispensation updated successfully.');
    }

    public function exportPdf(Request $request)
    {
        $user = Auth::user();

        // 1. REUSE FILTERS
        $query = Patientrecords::with(['dispensedMedications', 'barangay', 'branch']);

        // --- Branch Filtering ---
        if (in_array($user->user_level_id, [1, 2])) {
            if ($request->filled('branch_filter') && $request->branch_filter !== 'all') {
                $query->where('branch_id', $request->branch_filter);
            }
        } else {
            $query->where('branch_id', $user->branch_id);
        }

        // --- Date & Category Filters ---
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

        // 2. GET DATA
        $records = $query->latest()->get();

        // 3. GENERATE PDF
        $pdf = Pdf::loadView('admin.pdf.patientrecords_pdf', [
            'patientrecords' => $records,
            'generated_by' => $user->name,
            'date' => Carbon::now()->format('F d, Y'),
            'filters' => [
                'from' => $request->from_date,
                'to' => $request->to_date,
                'category' => $request->category,
            ]
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('patient_records_' . Carbon::now()->format('Ymd_His') . '.pdf');
    }
    
    public function exportExcel(Request $request)
    {
        $user = Auth::user();
        return Excel::download(new PatientRecordsExport($request->all(), $user), 'patient_records_' . Carbon::now()->format('Ymd_His') . '.xlsx');
    }

    public function viewSignature($filename)
    {
        // 1. Gamitin ang 'local' disk para sigurado (ito rin ang ginamit sa pag-save)
        $disk = Storage::disk('local');
        $folder = 'signatures/';

        // 2. Check kung nag-eexist ang file
        if (!$disk->exists($folder . $filename)) {
            // DEBUG: Kung 404 pa rin, pwede mong i-uncomment ito para makita kung saan siya naghahanap:
            // dd("File not found at: " . $disk->path($folder . $filename));
            
            abort(404);
        }

        // 3. Kunin ang full path para sa response
        $path = $disk->path($folder . $filename);

        return response()->file($path);
    }
}