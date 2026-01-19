<?php

namespace App\Repositories\Eloquent;

use App\Models\HistoryLog;
use App\Repositories\Contracts\HistoryLogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class HistoryLogRepository implements HistoryLogRepositoryInterface
{
    public function create(array $data): HistoryLog
    {
        return HistoryLog::create($data);
    }

    public function getRecentLogs(int $limit = 100): Collection
    {
        return HistoryLog::with('user')
                        ->orderBy('created_at', 'desc')
                        ->limit($limit)
                        ->get();
    }

    public function getByUser(int $userId): Collection
    {
        return HistoryLog::where('user_id', $userId)
                        ->orderBy('created_at', 'desc')
                        ->get();
    }

    public function getByAction(string $action): Collection
    {
        return HistoryLog::where('action', $action)
                        ->orderBy('created_at', 'desc')
                        ->get();
    }
}
