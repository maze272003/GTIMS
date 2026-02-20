<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMovement;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
// Imports for Exporting
use App\Exports\ProductMovementsExport; 
use Maatwebsite\Excel\Facades\Excel; 

class ProductMovementController extends Controller
{
    public function showMovements(Request $request)
    {
        // 1. Capture all filter inputs
        $search = $request->input('search', '');
        $product_id = $request->input('product_id', '');
        $type = $request->input('type', '');
        $user_id = $request->input('user_id', '');
        $branch_id = $request->input('branch_id', ''); 
        $from = $request->input('from', '');
        $to = $request->input('to', '');
        $sort = $request->input('sort', 'desc');

        // 2. EXPORT LOGIC: Check if this is an Export Request
        if ($request->has('export') && $request->get('export') == 'excel') {
            
            // Pass all current filters to the Export Class
            $params = $request->all();
            $fileName = 'movements_report_' . now()->format('Y-m-d_His') . '.xlsx';
            
            return Excel::download(new ProductMovementsExport($params), $fileName);
        }

        // 3. NORMAL PAGE LOGIC: Build the Query
        $query = ProductMovement::with(['product', 'user', 'inventory'])
            ->orderBy('created_at', $sort);

        // -- Apply Search --
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('inventory', fn($q_inv) => $q_inv->where('batch_number', 'like', "%{$search}%"));
            });
        }

        // -- Apply Dropdown Filters --
        if (!empty($product_id)) $query->where('product_id', $product_id);
        if (!empty($type)) $query->where('type', $type);
        if (!empty($user_id)) $query->where('user_id', $user_id);

        // -- Apply Branch Filter --
        if (!empty($branch_id)) {
            $query->whereHas('inventory', fn($q) => $q->where('branch_id', $branch_id));
        }

        // -- Apply Date Range --
        if (!empty($from) && !empty($to)) {
            $query->whereBetween('created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay()
            ]);
        } elseif (!empty($from)) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        } elseif (!empty($to)) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        // 4. Fetch Paginated Results
        $movements = $query->paginate(20)->withQueryString();

        // 5. Calculate Statistics (Today)
        $today = Carbon::today();
        $movementsTodayCount = ProductMovement::whereDate('created_at', $today)->count();
        $itemsInToday = ProductMovement::where('type', 'IN')->whereDate('created_at', $today)->sum('quantity');
        $itemsOutToday = ProductMovement::where('type', 'OUT')->whereDate('created_at', $today)->sum('quantity');

        // 6. Fetch Data for Filter Dropdowns
        $products = Product::where('is_archived', 0)->orderBy('generic_name')->get();
        $users = User::orderBy('name')->get();

        // 7. Return View
        if ($request->ajax()) {
            return view('admin.partials.movements_table', compact('movements'))->render();
        }

        return view('admin.product_movements', compact(
            'movements',
            'products',
            'users',
            'movementsTodayCount',
            'itemsInToday',
            'itemsOutToday'
        ));
    }
}