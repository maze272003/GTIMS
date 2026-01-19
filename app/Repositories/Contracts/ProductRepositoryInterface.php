<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;
    public function getAllActive(): Collection;
    public function getAllArchived(): Collection;
    public function findExisting(string $genericName, string $brandName, string $form, string $strength): ?Product;
    public function create(array $data): Product;
    public function update(Product $product, array $data): bool;
    public function archive(Product $product): bool;
    public function unarchive(Product $product): bool;
    public function delete(Product $product): bool;
}
