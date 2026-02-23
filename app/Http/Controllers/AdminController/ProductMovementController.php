<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use App\Services\ProductMovementQueryService;
use Illuminate\Http\Request;

class ProductMovementController extends Controller
{
    public function __construct(
        protected ProductMovementQueryService $productMovementQueryService
    ) {
    }

    public function showMovements(Request $request)
    {
        if ($request->has('export') && $request->get('export') == 'excel') {
            return $this->productMovementQueryService->export($request->all());
        }

        $data = $this->productMovementQueryService->getIndexData($request->only([
            'search',
            'product_id',
            'type',
            'user_id',
            'branch_id',
            'from',
            'to',
            'sort',
        ]));

        if ($request->ajax()) {
            return view('admin.partials.movements_table', [
                'movements' => $data['movements'],
            ])->render();
        }

        return view('admin.product_movements', $data);
    }
}

