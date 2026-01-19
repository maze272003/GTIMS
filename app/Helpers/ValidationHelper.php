<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ValidationHelper
{
    /**
     * Common product validation rules
     */
    public static function productRules(): array
    {
        return [
            'generic_name' => 'required|string|min:3|max:120',
            'brand_name' => 'required|string|min:3|max:120',
            'form' => 'required|string|min:3|max:120',
            'strength' => 'required|string|min:3|max:120',
        ];
    }

    /**
     * Product validation messages
     */
    public static function productMessages(): array
    {
        return [
            'generic_name.required' => 'Generic name is required.',
            'generic_name.min' => 'Generic name must be at least 3 characters.',
            'generic_name.max' => 'Generic name cannot exceed 120 characters.',
            'brand_name.required' => 'Brand name is required.',
            'brand_name.min' => 'Brand name must be at least 3 characters.',
            'brand_name.max' => 'Brand name cannot exceed 120 characters.',
            'form.required' => 'Form is required.',
            'form.min' => 'Form must be at least 3 characters.',
            'form.max' => 'Form cannot exceed 120 characters.',
            'strength.required' => 'Strength is required.',
            'strength.min' => 'Strength must be at least 3 characters.',
            'strength.max' => 'Strength cannot exceed 120 characters.',
        ];
    }

    /**
     * Common inventory stock validation rules
     */
    public static function inventoryStockRules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|in:1,2',
            'batch_number' => 'required|string|min:3|max:120',
            'quantity' => 'required|numeric|min:0',
            'expiry_date' => 'required|date|after:today',
        ];
    }

    /**
     * Inventory edit stock validation rules
     */
    public static function inventoryEditStockRules(): array
    {
        return [
            'inventory_id' => 'required|exists:inventories,id',
            'batch_number' => 'required|string|min:3|max:120',
            'quantity' => 'required|numeric|min:0',
            'expiry_date' => 'required|date|after:today',
        ];
    }

    /**
     * Inventory transfer stock validation rules
     */
    public static function inventoryTransferStockRules(): array
    {
        return [
            'inventory_id' => 'required|exists:inventories,id',
            'quantity' => 'required|numeric|min:1',
            'destination_branch' => 'required|in:1,2',
        ];
    }

    /**
     * Inventory stock validation messages
     */
    public static function inventoryStockMessages(): array
    {
        return [
            'product_id.required' => 'Product is required.',
            'product_id.exists' => 'Selected product does not exist.',
            'branch_id.required' => 'Branch is required.',
            'branch_id.in' => 'Invalid branch selected.',
            'batch_number.required' => 'Batch number is required.',
            'batch_number.min' => 'Batch number must be at least 3 characters.',
            'batch_number.max' => 'Batch number cannot exceed 120 characters.',
            'quantity.required' => 'Quantity is required.',
            'quantity.numeric' => 'Quantity must be a number.',
            'quantity.min' => 'Quantity cannot be negative.',
            'expiry_date.required' => 'Expiry date is required.',
            'expiry_date.date' => 'Expiry date must be a valid date.',
            'expiry_date.after' => 'Expiry date must be in the future.',
        ];
    }

    /**
     * Common patient record validation rules
     */
    public static function patientRecordRules(): array
    {
        return [
            'patient_name' => 'required|string|max:255',
            'barangay_id' => 'required|exists:barangays,id',
            'purok' => 'required|string|max:255',
            'category' => 'required|in:Adult,Child,Senior',
            'date_dispensed' => 'required|date',
        ];
    }

    /**
     * Patient record validation messages
     */
    public static function patientRecordMessages(): array
    {
        return [
            'patient_name.required' => 'Patient name is required.',
            'patient_name.max' => 'Patient name cannot exceed 255 characters.',
            'barangay_id.required' => 'Barangay is required.',
            'barangay_id.exists' => 'Selected barangay does not exist.',
            'purok.required' => 'Purok is required.',
            'purok.max' => 'Purok cannot exceed 255 characters.',
            'category.required' => 'Category is required.',
            'category.in' => 'Invalid category selected.',
            'date_dispensed.required' => 'Date dispensed is required.',
            'date_dispensed.date' => 'Date dispensed must be a valid date.',
        ];
    }

    /**
     * Medication validation rules for arrays
     */
    public static function medicationRules(): array
    {
        return [
            'medications' => 'required|array|min:1',
            'medications.*.inventory_id' => 'required|exists:inventories,id',
            'medications.*.quantity' => 'required|integer|min:1',
        ];
    }

    /**
     * Medication validation messages
     */
    public static function medicationMessages(): array
    {
        return [
            'medications.required' => 'At least one medication is required.',
            'medications.min' => 'At least one medication is required.',
            'medications.*.inventory_id.required' => 'Medicine selection is required.',
            'medications.*.inventory_id.exists' => 'Selected medicine does not exist.',
            'medications.*.quantity.required' => 'Quantity is required.',
            'medications.*.quantity.integer' => 'Quantity must be a whole number.',
            'medications.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
