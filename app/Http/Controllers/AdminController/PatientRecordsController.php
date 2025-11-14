<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Patientrecords;
use App\Models\Dispensedmedication;
use App\Models\ProductMovement; // <-- ADD THIS
use App\Models\Barangay;
use Illuminate\Support\Facades\Auth; // <-- ADD THIS

class PatientRecordsController extends Controller
{
    public function showpatientrecords(Request $request)
    {
        $products = Inventory::with('product')->where('is_archived', 2)->get(); 
        $barangays = Barangay::all();
        $patientrecords = Patientrecords::with(['dispensedMedications', 'barangay'])->paginate(20);
        $patientrecordscard = Patientrecords::with(['dispensedMedications', 'barangay'])->get();


        // count all dispensed medications
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
        ]);
    }

    public function adddispensation(Request $request) {
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
        $medicationsDetails = [];
        $user_id = Auth::id(); 

        // Check inventory first
        foreach ($validated['medications'] as $med) {
            $inventory = Inventory::findOrFail($med['name']);
            if ($inventory->quantity < $med['quantity']) {
                return back()->withErrors(['medications' => 'Insufficient quantity for ' . ($inventory->product->generic_name ?? 'medicine') . '. Available: ' . $inventory->quantity], 'adddispensation')->withInput();
            }
        }

        // Create PatientRecord
        $newRecord = Patientrecords::create([
            'patient_name' => $validated['patient-name'],
            'barangay_id' => $validated['barangay_id'],
            'purok' => $validated['purok'],
            'category' => $validated['category'],
            'date_dispensed' => $validated['date-dispensed'],
        ]);

        $medicationsDetails = [];
        // Create dispensed medications and deduct inventory
        foreach ($validated['medications'] as $med) {
            $inventory = Inventory::findOrFail($med['name']);
            // === START: CAPTURE QUANTITIES FOR LOGGING ===
            $quantity_before = $inventory->quantity;
            $quantity_to_deduct = $med['quantity'];
            $quantity_after = $quantity_before - $quantity_to_deduct;
            // === END: CAPTURE QUANTITIES ===

            // Deduct inventory
            $inventory->quantity = $quantity_after; // Use the calculated new quantity
            $inventory->save();

            // === START: LOG TO PRODUCT MOVEMENT TABLE ===
            ProductMovement::create([
                'product_id'      => $inventory->product_id,
                'inventory_id'    => $inventory->id,
                'user_id'         => $user_id,
                'type'            => 'OUT',
                'quantity'        => $quantity_to_deduct, // The amount that moved
                'quantity_before' => $quantity_before,
                'quantity_after'  => $quantity_after,
                'description'     => "Dispensed to Patient: {$newRecord->patient_name} (Record: #{$newRecord->id})",
            ]);
            // === END: LOG TO PRODUCT MOVEMENT TABLE ===

            $dispensedMed = Dispensedmedication::create([
                'patientrecord_id' => $newRecord->id,
                'barangay_id' => $validated['barangay_id'],
                'batch_number' => $inventory->batch_number ?? 'N/A',
                'generic_name' => $inventory->product->generic_name ?? 'N/A',
                'brand_name' => $inventory->product->brand_name ?? 'N/A',
                'strength' => $inventory->product->strength ?? 'N/A',
                'form' => $inventory->product->form ?? 'N/A',
                'quantity' => $med['quantity'],
            ]);

            $medicationsDetails[] = [
                'id' => $dispensedMed->id,
                'generic_name' => $dispensedMed->generic_name,
                'quantity' => $dispensedMed->quantity,
            ];
        }

        return to_route('admin.patientrecords')->with('success', 'Dispensation recorded successfully.');
    }
}