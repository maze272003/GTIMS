<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id): ?Product
    {
        return Product::find($id);
    }

    public function getAllActive(): Collection
    {
        return Product::where('is_archived', 0)->get();
    }

    public function getAllArchived(): Collection
    {
        return Product::where('is_archived', 1)->get();
    }

    public function findExisting(string $genericName, string $brandName, string $form, string $strength): ?Product
    {
        return Product::where('generic_name', $genericName)
                     ->where('brand_name', $brandName)
                     ->where('form', $form)
                     ->where('strength', $strength)
                     ->first();
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): bool
    {
        return $product->update($data);
    }

    public function archive(Product $product): bool
    {
        return $product->update(['is_archived' => 1]);
    }

    public function unarchive(Product $product): bool
    {
        return $product->update(['is_archived' => 0]);
    }

    public function delete(Product $product): bool
    {
        return $product->delete();
    }
}
