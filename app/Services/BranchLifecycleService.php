<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchArchivalRun;
use App\Models\HistoryLog;
use App\Models\Hold;
use App\Models\HoldItem;
use App\Models\IncomingRequest;
use App\Models\Inventory;
use App\Models\LowStockSetting;
use App\Models\Order;
use App\Models\Patientrecords;
use App\Models\ProductMovement;
use App\Models\ReorderRule;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BranchLifecycleService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    public function getIndexData(): array
    {
        $branches = Branch::query()
            ->with('archivedByUser:id,name')
            ->withCount([
                'users',
                'inventories',
                'orders',
            ])
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        $runs = BranchArchivalRun::query()
            ->with([
                'sourceBranch:id,name,code',
                'targetBranch:id,name,code',
                'initiator:id,name',
            ])
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        $mainBranch = Branch::query()->active()->main()->first();

        return [
            'branches' => $branches,
            'runs' => $runs,
            'mainBranch' => $mainBranch,
        ];
    }

    public function createBranch(array $payload, User $actor): Branch
    {
        return DB::transaction(function () use ($payload, $actor): Branch {
            $name = trim((string) $payload['name']);
            $code = trim((string) ($payload['code'] ?? ''));

            if ($code === '') {
                $code = Str::slug($name);
            }

            if ($code === '') {
                $code = 'branch-'.Str::lower(Str::random(8));
            }

            $isMain = (bool) ($payload['is_main'] ?? false);

            if ($isMain) {
                Branch::query()->where('is_main', true)->update(['is_main' => false]);
            }

            $branch = Branch::query()->create([
                'name' => $name,
                'code' => $code,
                'is_main' => $isMain,
                'is_archived' => false,
            ]);

            if (!Branch::query()->main()->exists()) {
                $branch->update(['is_main' => true]);
            }

            $this->auditService->record(
                'branch_created',
                'branch',
                (int) $branch->id,
                (int) $actor->id,
                null,
                $branch->fresh()?->toArray(),
                null,
                ['branch_code' => $branch->code]
            );

            HistoryLog::create([
                'action' => 'BRANCH CREATED',
                'description' => "Created branch {$branch->name} ({$branch->code}).",
                'user_id' => $actor->id,
                'user_name' => $actor->name,
                'metadata' => [
                    'branch_id' => $branch->id,
                    'branch_code' => $branch->code,
                ],
            ]);

            return $branch;
        });
    }

    public function setMainBranch(Branch $branch, User $actor): Branch
    {
        if ($branch->is_archived) {
            throw new RuntimeException('Archived branches cannot be set as main.');
        }

        $before = $branch->toArray();

        DB::transaction(function () use ($branch): void {
            Branch::query()->where('is_main', true)->update(['is_main' => false]);
            $branch->update(['is_main' => true]);
        });

        $branch = $branch->fresh();

        $this->auditService->record(
            'branch_set_main',
            'branch',
            (int) $branch->id,
            (int) $actor->id,
            $before,
            $branch->toArray(),
            null
        );

        HistoryLog::create([
            'action' => 'MAIN BRANCH UPDATED',
            'description' => "Set {$branch->name} as the designated main branch.",
            'user_id' => $actor->id,
            'user_name' => $actor->name,
            'metadata' => [
                'branch_id' => $branch->id,
            ],
        ]);

        return $branch;
    }

    public function archiveBranch(Branch $sourceBranch, User $actor, ?int $targetBranchId = null, ?string $reason = null): BranchArchivalRun
    {
        if ($sourceBranch->is_archived) {
            throw new RuntimeException('Selected branch is already archived.');
        }

        if ($sourceBranch->is_main) {
            throw new RuntimeException('The designated main branch cannot be archived.');
        }

        $targetBranchQuery = Branch::query()->active()->main();
        if ($targetBranchId) {
            $targetBranchQuery->where('id', $targetBranchId);
        }
        $targetBranch = $targetBranchQuery->first();

        if (!$targetBranch) {
            throw new RuntimeException('A designated active main branch is required before archival.');
        }

        if ((int) $targetBranch->id === (int) $sourceBranch->id) {
            throw new RuntimeException('Source and target branches must be different.');
        }

        $beforeMetrics = $this->collectMetrics((int) $sourceBranch->id, (int) $targetBranch->id);
        $beforeChecksum = $this->checksum($beforeMetrics);

        $run = BranchArchivalRun::query()->create([
            'source_branch_id' => $sourceBranch->id,
            'target_branch_id' => $targetBranch->id,
            'initiated_by' => $actor->id,
            'status' => 'in_progress',
            'progress_percent' => 5,
            'steps' => [
                $this->step('snapshot_collected', 'completed', [
                    'source_branch_id' => $sourceBranch->id,
                    'target_branch_id' => $targetBranch->id,
                ]),
            ],
            'before_metrics' => $beforeMetrics,
            'before_checksum' => $beforeChecksum,
            'metadata' => [
                'reason' => $reason,
            ],
            'started_at' => now(),
        ]);

        $this->auditService->record(
            'branch_archive_started',
            'branch',
            (int) $sourceBranch->id,
            (int) $actor->id,
            null,
            null,
            $reason,
            [
                'run_id' => $run->id,
                'target_branch_id' => $targetBranch->id,
                'before_checksum' => $beforeChecksum,
            ]
        );

        try {
            DB::transaction(function () use ($run, $sourceBranch, $targetBranch, $actor, $reason): void {
                $inventoryMapping = $this->consolidateInventories($sourceBranch, $targetBranch);
                $this->updateRunProgress(
                    $run,
                    40,
                    'inventory_consolidated',
                    [
                        'mapped_inventory_rows' => count($inventoryMapping),
                    ]
                );

                $this->reassignInventoryDependencies($inventoryMapping);
                $this->updateRunProgress(
                    $run,
                    60,
                    'inventory_dependencies_reassigned',
                    [
                        'source_inventory_rows' => count($inventoryMapping),
                    ]
                );

                $this->migrateBranchLinkedRecords((int) $sourceBranch->id, (int) $targetBranch->id);
                $this->updateRunProgress(
                    $run,
                    80,
                    'branch_linked_records_migrated',
                    []
                );

                $afterMetrics = $this->collectMetrics((int) $sourceBranch->id, (int) $targetBranch->id);
                $afterChecksum = $this->checksum($afterMetrics);
                $validation = $this->validateMigration($run->before_metrics ?? [], $afterMetrics);

                if (!$validation['ok']) {
                    throw new RuntimeException('Checksum validation failed: '.implode('; ', $validation['errors']));
                }

                $this->updateRunProgress(
                    $run,
                    95,
                    'checksum_validated',
                    [
                        'before_checksum' => $run->before_checksum,
                        'after_checksum' => $afterChecksum,
                        'checks' => $validation['checks'],
                    ]
                );

                $branchBefore = $sourceBranch->toArray();

                $sourceBranch->forceFill([
                    'is_archived' => true,
                    'archived_at' => now(),
                    'archived_by' => $actor->id,
                    'archive_checksum' => $afterChecksum,
                    'archive_metadata' => [
                        'run_id' => $run->id,
                        'target_branch_id' => $targetBranch->id,
                    ],
                ])->save();

                $this->updateRunProgress(
                    $run,
                    100,
                    'branch_archived',
                    [
                        'source_branch_id' => $sourceBranch->id,
                        'target_branch_id' => $targetBranch->id,
                    ]
                );

                $run->forceFill([
                    'status' => 'completed',
                    'after_metrics' => $afterMetrics,
                    'after_checksum' => $afterChecksum,
                    'completed_at' => now(),
                ])->save();

                $this->auditService->record(
                    'branch_archived',
                    'branch',
                    (int) $sourceBranch->id,
                    (int) $actor->id,
                    $branchBefore,
                    $sourceBranch->fresh()?->toArray(),
                    $reason,
                    [
                        'run_id' => $run->id,
                        'target_branch_id' => $targetBranch->id,
                        'before_checksum' => $run->before_checksum,
                        'after_checksum' => $afterChecksum,
                    ]
                );

                HistoryLog::create([
                    'action' => 'BRANCH ARCHIVED',
                    'description' => "Archived branch {$sourceBranch->name}. Data migrated to {$targetBranch->name}.",
                    'user_id' => $actor->id,
                    'user_name' => $actor->name,
                    'metadata' => [
                        'branch_id' => $sourceBranch->id,
                        'target_branch_id' => $targetBranch->id,
                        'run_id' => $run->id,
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'progress_percent' => min(99, max(5, (int) $run->progress_percent)),
                'error_message' => $exception->getMessage(),
                'failed_at' => now(),
            ])->save();

            $this->auditService->record(
                'branch_archive_failed',
                'branch',
                (int) $sourceBranch->id,
                (int) $actor->id,
                null,
                null,
                $reason,
                [
                    'run_id' => $run->id,
                    'target_branch_id' => $targetBranch->id,
                    'error' => $exception->getMessage(),
                ]
            );

            throw $exception;
        }

        return $run->fresh([
            'sourceBranch:id,name,code',
            'targetBranch:id,name,code',
            'initiator:id,name',
        ]);
    }

    public function rollbackFailedRun(BranchArchivalRun $run, User $actor, ?string $reason = null): BranchArchivalRun
    {
        if ($run->status !== 'failed') {
            throw new RuntimeException('Rollback is only available for failed archival runs.');
        }

        $before = $run->toArray();

        $metadata = $run->metadata ?? [];
        $metadata['rollback'] = [
            'user_id' => $actor->id,
            'reason' => $reason,
            'at' => now()->toISOString(),
        ];

        $run->forceFill([
            'status' => 'rolled_back',
            'rolled_back_at' => now(),
            'metadata' => $metadata,
        ])->save();

        $this->auditService->record(
            'branch_archive_rollback_marked',
            'branch_archival_run',
            (int) $run->id,
            (int) $actor->id,
            $before,
            $run->fresh()?->toArray(),
            $reason
        );

        HistoryLog::create([
            'action' => 'BRANCH ARCHIVAL ROLLBACK',
            'description' => "Marked archival run #{$run->id} as rolled back after failure.",
            'user_id' => $actor->id,
            'user_name' => $actor->name,
            'metadata' => [
                'run_id' => $run->id,
            ],
        ]);

        return $run->fresh([
            'sourceBranch:id,name,code',
            'targetBranch:id,name,code',
            'initiator:id,name',
        ]);
    }

    /**
     * @return array<int,int> map: source inventory id => target inventory id
     */
    private function consolidateInventories(Branch $sourceBranch, Branch $targetBranch): array
    {
        $sourceInventories = Inventory::query()
            ->where('branch_id', $sourceBranch->id)
            ->orderBy('id')
            ->get();

        $targetInventories = Inventory::query()
            ->where('branch_id', $targetBranch->id)
            ->get()
            ->keyBy(fn (Inventory $inventory) => $this->inventoryKey($inventory));

        $mapping = [];

        foreach ($sourceInventories as $sourceInventory) {
            $key = $this->inventoryKey($sourceInventory);
            $targetInventory = $targetInventories->get($key);

            if ($targetInventory) {
                $targetInventory->quantity += (int) $sourceInventory->quantity;
                $targetInventory->save();
            } else {
                $targetInventory = Inventory::query()->create([
                    'product_id' => $sourceInventory->product_id,
                    'branch_id' => $targetBranch->id,
                    'batch_number' => $sourceInventory->batch_number,
                    'quantity' => $sourceInventory->quantity,
                    'expiry_date' => $sourceInventory->expiry_date,
                    'is_archived' => $sourceInventory->is_archived,
                    'created_at' => $sourceInventory->created_at,
                    'updated_at' => now(),
                ]);

                $targetInventories->put($key, $targetInventory);
            }

            $mapping[(int) $sourceInventory->id] = (int) $targetInventory->id;
        }

        return $mapping;
    }

    /**
     * @param array<int,int> $mapping
     */
    private function reassignInventoryDependencies(array $mapping): void
    {
        foreach ($mapping as $sourceInventoryId => $targetInventoryId) {
            if ($sourceInventoryId === $targetInventoryId) {
                continue;
            }

            $this->mergeHoldItems($sourceInventoryId, $targetInventoryId);
            $this->mergeSupplierLinks($sourceInventoryId, $targetInventoryId);

            ProductMovement::query()
                ->where('inventory_id', $sourceInventoryId)
                ->update(['inventory_id' => $targetInventoryId]);
        }

        if (!empty($mapping)) {
            Inventory::query()->whereIn('id', array_keys($mapping))->delete();
        }
    }

    private function mergeHoldItems(int $sourceInventoryId, int $targetInventoryId): void
    {
        $holdItems = HoldItem::query()
            ->where('inventory_id', $sourceInventoryId)
            ->get();

        foreach ($holdItems as $holdItem) {
            $existing = HoldItem::query()
                ->where('hold_id', $holdItem->hold_id)
                ->where('product_id', $holdItem->product_id)
                ->where('inventory_id', $targetInventoryId)
                ->first();

            if ($existing) {
                $existing->quantity = (int) $existing->quantity + (int) $holdItem->quantity;
                $existing->save();
                $holdItem->delete();
                continue;
            }

            $holdItem->inventory_id = $targetInventoryId;
            $holdItem->save();
        }
    }

    private function mergeSupplierLinks(int $sourceInventoryId, int $targetInventoryId): void
    {
        $links = SupplierProduct::query()
            ->where('inventory_id', $sourceInventoryId)
            ->get();

        foreach ($links as $link) {
            $existing = SupplierProduct::query()
                ->where('supplier_id', $link->supplier_id)
                ->where('inventory_id', $targetInventoryId)
                ->first();

            if ($existing) {
                if ($existing->lead_time_days === null || ((int) $link->lead_time_days > 0 && (int) $link->lead_time_days < (int) $existing->lead_time_days)) {
                    $existing->lead_time_days = $link->lead_time_days;
                }

                if ($existing->unit_cost === null && $link->unit_cost !== null) {
                    $existing->unit_cost = $link->unit_cost;
                }

                $existing->save();
                $link->delete();
                continue;
            }

            $link->inventory_id = $targetInventoryId;
            $link->save();
        }
    }

    private function migrateBranchLinkedRecords(int $sourceBranchId, int $targetBranchId): void
    {
        User::query()->where('branch_id', $sourceBranchId)->update(['branch_id' => $targetBranchId]);
        Order::query()->where('branch_id', $sourceBranchId)->update(['branch_id' => $targetBranchId]);
        Patientrecords::query()->where('branch_id', $sourceBranchId)->update(['branch_id' => $targetBranchId]);
        Hold::query()->where('branch_id', $sourceBranchId)->update(['branch_id' => $targetBranchId]);
        IncomingRequest::query()->where('branch_id', $sourceBranchId)->update(['branch_id' => $targetBranchId]);
        ReorderRule::query()->where('branch_id', $sourceBranchId)->update(['branch_id' => $targetBranchId]);

        $this->mergeLowStockSettings($sourceBranchId, $targetBranchId);
    }

    private function mergeLowStockSettings(int $sourceBranchId, int $targetBranchId): void
    {
        $sourceSettings = LowStockSetting::query()
            ->where('branch_id', $sourceBranchId)
            ->get();

        foreach ($sourceSettings as $setting) {
            $query = LowStockSetting::query()->where('branch_id', $targetBranchId);

            if ($setting->product_id === null) {
                $query->whereNull('product_id');
            } else {
                $query->where('product_id', $setting->product_id);
            }

            $targetSetting = $query->first();

            if ($targetSetting) {
                $targetSetting->threshold = max((int) $targetSetting->threshold, (int) $setting->threshold);
                $targetSetting->save();
                $setting->delete();
                continue;
            }

            $setting->branch_id = $targetBranchId;
            $setting->save();
        }
    }

    private function collectMetrics(int $sourceBranchId, int $targetBranchId): array
    {
        $sourceInventoryIds = Inventory::query()
            ->where('branch_id', $sourceBranchId)
            ->pluck('id');

        $targetInventoryRows = Inventory::query()->where('branch_id', $targetBranchId)->count();
        $targetInventoryQuantity = (int) Inventory::query()->where('branch_id', $targetBranchId)->sum('quantity');

        $sourceInventoryRows = $sourceInventoryIds->count();
        $sourceInventoryQuantity = $sourceInventoryRows > 0
            ? (int) Inventory::query()->whereIn('id', $sourceInventoryIds)->sum('quantity')
            : 0;

        return [
            'source' => [
                'inventory_rows' => $sourceInventoryRows,
                'inventory_quantity' => $sourceInventoryQuantity,
                'users' => User::query()->where('branch_id', $sourceBranchId)->count(),
                'orders' => Order::query()->where('branch_id', $sourceBranchId)->count(),
                'orders_pending' => Order::query()
                    ->where('branch_id', $sourceBranchId)
                    ->whereIn('status', ['pending_admin', 'pending_finance'])
                    ->count(),
                'patient_records' => Patientrecords::query()->where('branch_id', $sourceBranchId)->count(),
                'holds' => Hold::query()->where('branch_id', $sourceBranchId)->count(),
                'holds_pending' => Hold::query()
                    ->where('branch_id', $sourceBranchId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->count(),
                'incoming_requests' => IncomingRequest::query()->where('branch_id', $sourceBranchId)->count(),
                'incoming_requests_pending' => IncomingRequest::query()
                    ->where('branch_id', $sourceBranchId)
                    ->whereNotIn('status', ['fulfilled', 'closed', 'denied'])
                    ->count(),
                'low_stock_settings' => LowStockSetting::query()->where('branch_id', $sourceBranchId)->count(),
                'reorder_rules' => ReorderRule::query()->where('branch_id', $sourceBranchId)->count(),
            ],
            'target' => [
                'inventory_rows' => $targetInventoryRows,
                'inventory_quantity' => $targetInventoryQuantity,
            ],
            'dependencies' => [
                'hold_items_on_source_inventory' => $sourceInventoryIds->isEmpty()
                    ? 0
                    : HoldItem::query()->whereIn('inventory_id', $sourceInventoryIds)->count(),
                'supplier_links_on_source_inventory' => $sourceInventoryIds->isEmpty()
                    ? 0
                    : SupplierProduct::query()->whereIn('inventory_id', $sourceInventoryIds)->count(),
                'movements_on_source_inventory' => $sourceInventoryIds->isEmpty()
                    ? 0
                    : ProductMovement::query()->whereIn('inventory_id', $sourceInventoryIds)->count(),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @return array{ok: bool, checks: array<string,bool>, errors: array<int,string>}
     */
    private function validateMigration(array $before, array $after): array
    {
        $checks = [
            'source_inventory_cleared' => (int) data_get($after, 'source.inventory_rows', 0) === 0,
            'source_inventory_quantity_zero' => (int) data_get($after, 'source.inventory_quantity', 0) === 0,
            'source_users_cleared' => (int) data_get($after, 'source.users', 0) === 0,
            'source_orders_cleared' => (int) data_get($after, 'source.orders', 0) === 0,
            'source_patient_records_cleared' => (int) data_get($after, 'source.patient_records', 0) === 0,
            'source_holds_cleared' => (int) data_get($after, 'source.holds', 0) === 0,
            'source_requests_cleared' => (int) data_get($after, 'source.incoming_requests', 0) === 0,
            'source_low_stock_rules_cleared' => (int) data_get($after, 'source.low_stock_settings', 0) === 0,
            'source_reorder_rules_cleared' => (int) data_get($after, 'source.reorder_rules', 0) === 0,
            'inventory_links_cleared' => (int) data_get($after, 'dependencies.hold_items_on_source_inventory', 0) === 0
                && (int) data_get($after, 'dependencies.supplier_links_on_source_inventory', 0) === 0
                && (int) data_get($after, 'dependencies.movements_on_source_inventory', 0) === 0,
            'inventory_quantity_conserved' => (int) data_get($after, 'target.inventory_quantity', 0)
                === ((int) data_get($before, 'target.inventory_quantity', 0) + (int) data_get($before, 'source.inventory_quantity', 0)),
        ];

        $errors = [];

        foreach ($checks as $name => $result) {
            if (!$result) {
                $errors[] = $name;
            }
        }

        return [
            'ok' => empty($errors),
            'checks' => $checks,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string,mixed> $details
     * @return array<string,mixed>
     */
    private function step(string $name, string $status, array $details): array
    {
        return [
            'name' => $name,
            'status' => $status,
            'details' => $details,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * @param array<string,mixed> $details
     */
    private function updateRunProgress(BranchArchivalRun $run, int $progressPercent, string $stepName, array $details): void
    {
        $steps = $run->steps ?? [];
        $steps[] = $this->step($stepName, 'completed', $details);

        $run->forceFill([
            'progress_percent' => max(0, min(100, $progressPercent)),
            'steps' => $steps,
        ])->save();
    }

    private function inventoryKey(Inventory $inventory): string
    {
        return implode('|', [
            (string) $inventory->product_id,
            (string) $inventory->batch_number,
            optional($inventory->expiry_date)->format('Y-m-d') ?? (string) $inventory->expiry_date,
            (string) ((int) $inventory->is_archived),
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function checksum(array $payload): string
    {
        $normalized = $this->normalizeForChecksum($payload);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeForChecksum(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return [];
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);

        if ($isAssoc) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeForChecksum($item);
        }

        return $value;
    }
}
