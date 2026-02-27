<?php

namespace App\Http\Requests\Admin;

use App\Rules\BelongsToCurrentProvince;
use App\Rules\BelongsToCurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

class StoreHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id'            => ['required', 'exists:branches,id', new BelongsToCurrentTenant('branches')],
            'barangay_id'          => ['nullable', 'exists:barangays,id', new BelongsToCurrentProvince('barangays')],
            'type'                 => ['required', 'in:reservation,quarantine,recall'],
            'reason_code'          => ['required', 'string', 'max:255'],
            'remarks'              => ['nullable', 'string', 'max:2000'],
            'expires_at'           => ['nullable', 'date', 'after:now'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'exists:products,id'],
            'items.*.inventory_id' => ['required', 'exists:inventories,id', new BelongsToCurrentTenant('inventories')],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.quantity.min' => 'Each item quantity must be at least 1.',
            'type.in' => 'The hold type must be reservation, quarantine, or recall.',
            'expires_at.after' => 'The expiration date must be in the future.',
        ];
    }
}
