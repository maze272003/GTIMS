<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMovement;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;

class ProductMovementController extends Controller
{
    public function showMovements(Request $request)
    {
        $search = $request->input('search', '');
        $product_id = $request->input('product_id', '');
        $type = $request->input('type', '');
        $user_id = $request->input('user_id', '');
        $from = $request->input('from', '');
        $to = $request->input('to', '');

        $sort = $request->input('sort', 'desc');
        $query = ProductMovement::with(['product', 'user', 'inventory'])
                                ->orderBy('created_at', $sort);

        // === Search Filter (by description or batch) ===
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('inventory', function ($q_inv) use ($search) {
                      $q_inv->where('batch_number', 'like', "%{$search}%");
                  });
            });
        }
        
        // === Product Filter ===
        if (!empty($product_id)) {
            $query->where('product_id', $product_id);
        }

        // === Type Filter (IN/OUT) ===
        if (!empty($type)) {
            $query->where('type', $type);
        }
        
        // === User Filter ===
        if (!empty($user_id)) {
            $query->where('user_id', $user_id);
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

        $movements = $query->paginate(20)->withQueryString();

        // For dropdown data
        if ($request->ajax()) {
            return view('admin.partials._movements_table', compact('movements'))->render();
        }
        
        $products = Product::where('is_archived', 2)->orderBy('generic_name')->get();
        $users = User::orderBy('name')->get(); // Assuming you want all users

        return view('admin.product_movements', compact('movements', 'products', 'users'));
    }
}