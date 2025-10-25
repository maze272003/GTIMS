<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class InventoryController extends Controller
{
    public function showinventory()
    {
        return view('admin.inventory');
    }

    public function addProduct(Request $request, Product $product) {
        // add product
        $validated = $request->validate([
            'generic_name' => 'string|min:3|max:120|required',
            'brand_name' => 'string|min:3|max:120|required',
            'form' => 'string|min:3|max:120|required',
            'strength' => 'string|min:3|max:120|required',
        ])
        ;
        $product->create($validated);

        return to_route('admin.inventory')->with('success', 'Product added successfully.');
    }
}
