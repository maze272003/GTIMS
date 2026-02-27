<?php

namespace App\Rules;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class BelongsToCurrentBarangay implements ValidationRule
{
    public function __construct(
        protected string $table,
        protected string $idColumn = 'id',
        protected string $barangayColumn = 'barangay_id',
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = app()->bound(TenantContext::class) ? app(TenantContext::class) : null;

        $barangayId = $tenantContext?->barangayId;
        if (!$barangayId) {
            return;
        }

        $exists = DB::table($this->table)
            ->where($this->idColumn, $value)
            ->where($this->barangayColumn, $barangayId)
            ->exists();

        if (!$exists) {
            $fail('The selected :attribute does not belong to the current barangay.');
        }
    }
}

