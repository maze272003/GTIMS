<?php

namespace App\Rules;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class BelongsToCurrentProvince implements ValidationRule
{
    public function __construct(
        protected string $table,
        protected string $idColumn = 'id',
        protected string $provinceColumn = 'province_id',
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = app()->bound(TenantContext::class) ? app(TenantContext::class) : null;

        $provinceId = $tenantContext?->provinceId;
        if (!$provinceId) {
            return;
        }

        $exists = DB::table($this->table)
            ->where($this->idColumn, $value)
            ->where($this->provinceColumn, $provinceId)
            ->exists();

        if (!$exists) {
            $fail('The selected :attribute does not belong to the current province.');
        }
    }
}

