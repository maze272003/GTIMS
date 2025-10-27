<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HistoryLog;
use Carbon\Carbon;

class HistorylogController extends Controller
{
    public function showhistorylog(Request $request)
    {
        $search = $request->input('search', '');
        $action = $request->input('action', '');
        $user = $request->input('user', '');
        $from = $request->input('from', '');
        $to = $request->input('to', '');

        $sort = $request->input('sort', 'desc');
        $query = HistoryLog::query()->orderBy('created_at', $sort);

        // === Search Filter ===
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhereRaw("DATE_FORMAT(created_at, '%M %e, %Y %l:%i %p') LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("DATE_FORMAT(created_at, '%Y-%m-%d') LIKE ?", ["%{$search}%"]);
            });
        }

        // === Action Filter ===
        if (!empty($action)) {
            $query->where('action', $action);
        }

        // === User Filter ===
        if (!empty($user)) {
            $query->where('user_name', $user);
        }

        // === Date Range Filter ===
        if (!empty($from) && !empty($to)) {
            $fromDate = Carbon::parse($from)->startOfDay();
            $toDate = Carbon::parse($to)->endOfDay();
            $query->whereBetween('created_at', [$fromDate, $toDate]);
        } elseif (!empty($from)) {
            $fromDate = Carbon::parse($from)->startOfDay();
            $query->where('created_at', '>=', $fromDate);
        } elseif (!empty($to)) {
            $toDate = Carbon::parse($to)->endOfDay();
            $query->where('created_at', '<=', $toDate);
        }

        $historyLogs = $query->paginate(20)->withQueryString();

        // For dropdown data
        
        if ($request->ajax()) {
            return view('admin.partials._history_table', compact('historyLogs'))->render();
        }
        
        $actions = HistoryLog::select('action')->distinct()->pluck('action');
        $users = HistoryLog::select('user_name')->distinct()->pluck('user_name');

        return view('admin.historylog', compact('historyLogs', 'actions', 'users'));
    }
}
