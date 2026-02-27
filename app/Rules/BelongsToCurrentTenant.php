<?php

namespace App\Rules;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class BelongsToCurrentTenant implements ValidationRule
{
    public function __construct(
        protected string $table,
        protected string $idColumn = 'id',
        protected string $provinceColumn = 'province_id',
        protected string $barangayColumn = 'barangay_id',
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = app()->bound(TenantContext::class) ? app(TenantContext::class) : null;

        if (!$tenantContext || $tenantContext->isPlatform()) {
            return;
        }

        $query = DB::table($this->table)
            ->where($this->idColumn, $value)
            ->where(function ($provinceQuery) use ($tenantContext) {
                $provinceQuery->where($this->provinceColumn, $tenantContext->provinceId);

                if (config('tenancy.allow_legacy_unscoped_records', true)) {
                    $provinceQuery->orWhereNull($this->provinceColumn);
                }
            });

        if ($tenantContext->isBarangay()) {
            $query->where(function ($barangayQuery) use ($tenantContext) {
                $barangayQuery->where($this->barangayColumn, $tenantContext->barangayId);

                if (config('tenancy.allow_legacy_unscoped_records', true)) {
                    $barangayQuery->orWhereNull($this->barangayColumn);
                }
            });
        }

        if (!$query->exists()) {
            $fail('The selected :attribute does not belong to the current tenant.');
        }
    }
}
