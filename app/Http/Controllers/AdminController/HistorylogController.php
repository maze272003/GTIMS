<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use App\Services\HistoryLogQueryService;
use Illuminate\Http\Request;

class HistorylogController extends Controller
{
    public function __construct(
        protected HistoryLogQueryService $historyLogQueryService
    ) {
    }

    public function showhistorylog(Request $request)
    {
        $filters = $request->only(['search', 'action', 'user', 'from', 'to', 'sort']);
        $historyLogs = $this->historyLogQueryService->paginateWithFilters($filters, 20);

        if ($request->ajax()) {
            return view('admin.partials._history_table', compact('historyLogs'));
        }

        ['actions' => $actions, 'users' => $users] = $this->historyLogQueryService->getFilterOptions();

        return view('admin.historylog', compact('historyLogs', 'actions', 'users'));
    }
}

