<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Patientrecords;
use App\Models\Dispensedmedication;

class PatientRecordsController extends Controller
{
    public function showpatientrecords(Request $request)
    {
        $products = Inventory::with('product')->where('is_archived', 2)->get(); 
        $patientrecords = Patientrecords::with('dispensedMedications')->get();

        // count all dispensed medications

        $totalPeopleServed = $patientrecords->count();

        $totalProductsDispensed = $patientrecords->sum(function ($patientrecord) {
            return $patientrecord->dispensedMedications->count();
        });

        return view('admin.patientrecords', [
            'products' => $products,
            'patientrecords' => $patientrecords,
            'totalPeopleServed' => $totalPeopleServed,
            'totalProductsDispensed' => $totalProductsDispensed,
        ]);
    }

    public function adddispensation(Request $request) {
        $validated = $request->validateWithBag('adddispensation', [
            'patient-name' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'purok' => 'required|string|max:255',
            'category' => 'required|in:Adult,Child,Senior',
            'date-dispensed' => 'required|date',
            'medications' => 'required|array|min:1',
            'medications.*.name' => 'required|exists:inventories,id',
            'medications.*.quantity' => 'required|integer|min:1',
        ], [
            'patient-name.required' => 'Patient name is required.',
            'barangay.required' => 'Barangay is required.',
            'purok.required' => 'Purok is required.',
            'category.required' => 'Category is required.',
            'date-dispensed.required' => 'Date dispensed is required.',
            'medications.required' => 'At least one medication is required.',
            'medications.*.name.required' => 'Medicine selection is required.',
            'medications.*.quantity.required' => 'Quantity is required.',
        ]);

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
            'barangay' => $validated['barangay'],
            'purok' => $validated['purok'],
            'category' => $validated['category'],
            'date_dispensed' => $validated['date-dispensed'],
        ]);

        $medicationsDetails = [];
        // Create dispensed medications and deduct inventory
        foreach ($validated['medications'] as $med) {
            $inventory = Inventory::findOrFail($med['name']);
            $inventory->quantity -= $med['quantity'];
            $inventory->save();

            $dispensedMed = Dispensedmedication::create([
                'patientrecord_id' => $newRecord->id,
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