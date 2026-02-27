<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Shared helpers for applying and validating tenant scope.
 */
final class TenantScope
{
    /**
     * Apply province/barangay constraints to a query.
     */
    public static function apply(
        Builder $query,
        ?TenantContext $tenantContext,
        ?string $table = null,
        string $provinceColumn = 'province_id',
        string $barangayColumn = 'barangay_id'
    ): Builder {
        if (!$tenantContext || $tenantContext->isPlatform()) {
            return $query;
        }

        $tableName = $table ?: $query->getModel()->getTable();

        if (self::hasColumn($tableName, $provinceColumn) && $tenantContext->provinceId) {
            $query->where("{$tableName}.{$provinceColumn}", (int) $tenantContext->provinceId);
        }

        if (
            $tenantContext->isBarangay()
            && $tenantContext->barangayId
            && self::hasColumn($tableName, $barangayColumn)
        ) {
            $query->where("{$tableName}.{$barangayColumn}", (int) $tenantContext->barangayId);
        }

        return $query;
    }

    /**
     * Determine if a model row belongs to the current tenant context.
     */
    public static function modelBelongsToTenant(Model $model, ?TenantContext $tenantContext): bool
    {
        if (!$tenantContext || $tenantContext->isPlatform()) {
            return true;
        }

        $provinceId = (int) ($model->getAttribute('province_id') ?? 0);
        $barangayId = $model->getAttribute('barangay_id');

        if ($provinceId !== (int) $tenantContext->provinceId) {
            return false;
        }

        if ($tenantContext->isBarangay()) {
            return (int) $barangayId === (int) $tenantContext->barangayId;
        }

        return true;
    }

    /**
     * Determine whether a table should be tenant-validated.
     */
    public static function tenantColumnsExist(string $table): bool
    {
        return self::hasColumn($table, 'province_id');
    }

    protected static function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = "{$table}.{$column}";

        if (!array_key_exists($key, $cache)) {
            $cache[$key] = Schema::hasTable($table) && Schema::hasColumn($table, $column);
        }

        return $cache[$key];
    }
}

