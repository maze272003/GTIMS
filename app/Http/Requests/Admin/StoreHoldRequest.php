<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id'            => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'barangay_id'          => ['nullable', 'exists:barangays,id'],
            'type'                 => ['required', 'in:reservation,quarantine,recall'],
            'reason_code'          => ['required', 'string', 'max:255'],
            'remarks'              => ['nullable', 'string', 'max:2000'],
            'expires_at'           => ['nullable', 'date', 'after:now'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'exists:products,id'],
            'items.*.inventory_id' => ['required', 'exists:inventories,id'],
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
