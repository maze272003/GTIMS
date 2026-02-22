<?php

namespace App\Repositories\Eloquent;

use App\Models\HistoryLog;
use App\Repositories\Interfaces\HistoryLogRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class HistoryLogRepository extends BaseRepository implements HistoryLogRepositoryInterface
{
    public function __construct(HistoryLog $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $search = (string) ($filters['search'] ?? '');
        $action = (string) ($filters['action'] ?? '');
        $user = (string) ($filters['user'] ?? '');
        $from = (string) ($filters['from'] ?? '');
        $to = (string) ($filters['to'] ?? '');
        $sort = strtolower((string) ($filters['sort'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = $this->model->newQuery()->orderBy('created_at', $sort);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");

                if (DB::connection()->getDriverName() !== 'sqlite') {
                    $q->orWhereRaw("DATE_FORMAT(created_at, '%M %e, %Y %l:%i %p') LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("DATE_FORMAT(created_at, '%Y-%m-%d') LIKE ?", ["%{$search}%"]);
                } else {
                    $q->orWhere('created_at', 'like', "%{$search}%");
                }
            });
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($user !== '') {
            $query->where('user_name', $user);
        }

        if ($from !== '' && $to !== '') {
            $query->whereBetween('created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ]);
        } elseif ($from !== '') {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        } elseif ($to !== '') {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        return $query->paginate($perPage);
    }

    public function getDistinctActions(): Collection
    {
        return $this->model->newQuery()->select('action')->distinct()->pluck('action');
    }

    public function getDistinctUsers(): Collection
    {
        return $this->model->newQuery()->select('user_name')->distinct()->pluck('user_name');
    }
}
