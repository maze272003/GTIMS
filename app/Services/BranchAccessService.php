<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class BranchAccessService
{
    public function __construct(
        private readonly AuthSessionService $authSessionService
    ) {
    }

    public function canAccessAllBranches(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasPermission('branches.manage')) {
            return true;
        }

        return strtolower((string) ($user->level?->name ?? '')) === 'superadmin';
    }

    public function branchId(?User $user): ?int
    {
        $branchId = $user?->branch_id;

        return is_numeric($branchId) && (int) $branchId > 0
            ? (int) $branchId
            : null;
    }

    /**
     * @return array<int, int>
     */
    public function accessibleBranchIds(?User $user): array
    {
        if ($this->canAccessAllBranches($user)) {
            return Branch::query()
                ->active()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $branchId = $this->branchId($user);

        return $branchId ? [$branchId] : [];
    }

    public function visibleBranches(?User $user): Collection
    {
        $query = Branch::query()
            ->active()
            ->orderBy('name');

        if ($this->canAccessAllBranches($user)) {
            return $query->get();
        }

        $branchId = $this->branchId($user);

        if (!$branchId) {
            return Branch::newCollection();
        }

        return $query->where('id', $branchId)->get();
    }

    public function scopeQueryToUserBranch(Builder $query, ?User $user, string $column = 'branch_id'): Builder
    {
        if ($this->canAccessAllBranches($user)) {
            return $query;
        }

        $branchId = $this->branchId($user);

        if (!$branchId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($column, $branchId);
    }

    public function scopeQueryByRelationBranch(
        Builder $query,
        ?User $user,
        string $relation = 'inventory',
        string $column = 'branch_id'
    ): Builder {
        if ($this->canAccessAllBranches($user)) {
            return $query;
        }

        $branchId = $this->branchId($user);

        if (!$branchId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas($relation, fn (Builder $relationQuery) => $relationQuery->where($column, $branchId));
    }

    public function resolveBranchFilter(?User $user, mixed $requestedBranchId, bool $defaultToUserBranch = true): ?int
    {
        if ($this->canAccessAllBranches($user)) {
            return $this->normalizeBranchId($requestedBranchId);
        }

        $branchId = $this->branchId($user);

        if (!$branchId) {
            $this->deny($user, null, 'access branch-specific data');
        }

        $requested = $this->normalizeBranchId($requestedBranchId);

        if ($requested === null) {
            return $defaultToUserBranch ? $branchId : null;
        }

        if ($requested !== $branchId) {
            $this->deny($user, $requested, 'access data from another branch');
        }

        return $branchId;
    }

    public function authorizeBranchAccess(?User $user, mixed $targetBranchId, string $subject = 'access this record'): void
    {
        $normalizedTargetBranchId = $this->normalizeBranchId($targetBranchId);

        if ($normalizedTargetBranchId === null || $this->canAccessAllBranches($user)) {
            return;
        }

        $branchId = $this->branchId($user);

        if (!$branchId || $normalizedTargetBranchId !== $branchId) {
            $this->deny($user, $normalizedTargetBranchId, $subject);
        }
    }

    public function authorizeModel(?User $user, Model $model, string $subject = 'access this record', ?string $branchPath = null): void
    {
        $targetBranchId = $branchPath
            ? data_get($model, $branchPath)
            : data_get($model, 'branch_id');

        if ($targetBranchId === null) {
            $targetBranchId = data_get($model, 'inventory.branch_id');
        }

        $this->authorizeBranchAccess($user, $targetBranchId, $subject);
    }

    public function authorizeGlobalBranchAccess(?User $user, string $subject = 'perform this all-branch action'): void
    {
        if ($this->canAccessAllBranches($user)) {
            return;
        }

        $this->deny($user, null, $subject);
    }

    private function normalizeBranchId(mixed $branchId): ?int
    {
        if (!is_numeric($branchId) || (int) $branchId <= 0) {
            return null;
        }

        return (int) $branchId;
    }

    private function deny(?User $user, ?int $targetBranchId, string $subject): never
    {
        $this->logDeniedAttempt($user, $targetBranchId, $subject);

        abort(403, $this->authSessionService->getForbiddenMessage($user, $subject));
    }

    private function logDeniedAttempt(?User $user, ?int $targetBranchId, string $subject): void
    {
        $context = [
            'user_id' => $user?->id,
            'user_branch_id' => $this->branchId($user),
            'target_branch_id' => $targetBranchId,
            'subject' => $subject,
            'route' => request()?->route()?->getName(),
            'path' => request()?->path(),
            'method' => request()?->method(),
            'ip' => request()?->ip(),
        ];

        Log::warning('Branch access denied.', $context);

        if (!$user) {
            return;
        }

        try {
            AuditEvent::create([
                'action' => 'branch.access.denied',
                'entity_type' => 'authorization',
                'entity_id' => $targetBranchId,
                'user_id' => $user->id,
                'before' => null,
                'after' => null,
                'reason' => $subject,
                'metadata' => $context,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to persist denied branch access audit event.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
