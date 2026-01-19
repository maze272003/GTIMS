<?php

namespace App\Repositories\Contracts;

use App\Models\HistoryLog;
use Illuminate\Database\Eloquent\Collection;

interface HistoryLogRepositoryInterface
{
    public function create(array $data): HistoryLog;
    public function getRecentLogs(int $limit = 100): Collection;
    public function getByUser(int $userId): Collection;
    public function getByAction(string $action): Collection;
}
