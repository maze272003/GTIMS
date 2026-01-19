<?php

namespace App\Services\Implementations;

use App\Services\Contracts\InventoryServiceInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ProductMovementRepositoryInterface;
use App\Repositories\Contracts\HistoryLogRepositoryInterface;
use App\Helpers\HistoryLogHelper;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use Exception;

class InventoryService implements InventoryServiceInterface
{
    protected InventoryRepositoryInterface $inventoryRepository;
    protected ProductRepositoryInterface $productRepository;
    protected ProductMovementRepositoryInterface $productMovementRepository;
    protected HistoryLogRepositoryInterface $historyLogRepository;

    public function __construct(
        InventoryRepositoryInterface $inventoryRepository,
        ProductRepositoryInterface $productRepository,
        ProductMovementRepositoryInterface $productMovementRepository,
        HistoryLogRepositoryInterface $historyLogRepository
    ) {
        $this->inventoryRepository = $inventoryRepository;
        $this->productRepository = $productRepository;
        $this->productMovementRepository = $productMovementRepository;
        $this->historyLogRepository = $historyLogRepository;
    }

    public function getInventoryData(Request $request): array
    {
        $products = $this->productRepository->getAllActive();
        $archiveproducts = $this->productRepository->getAllArchived();
        $inventorycount = $this->inventoryRepository->getAllActive();

        $inventories_rhu1 = $this->inventoryRepository->getByBranchPaginated(
            1,
            ['search' => $request->search_rhu1, 'filter' => $request->filter_rhu1],
            20
        );
        $inventories_rhu1->appends(['page_rhu1' => $inventories_rhu1->currentPage()]);

        $inventories_rhu2 = $this->inventoryRepository->getByBranchPaginated(
            2,
            ['search' => $request->search_rhu2, 'filter' => $request->filter_rhu2],
            20
        );
        $inventories_rhu2->appends(['page_rhu2' => $inventories_rhu2->currentPage()]);

        return [
            'products' => $products,
            'archiveproducts' => $archiveproducts,
            'inventorycount' => $inventorycount,
            'inventories_rhu1' => $inventories_rhu1,
            'inventories_rhu2' => $inventories_rhu2
        ];
    }

    public function getArchivedStocks(int $productId): LengthAwarePaginator
    {
        return $this->inventoryRepository->getArchivedByProduct($productId);
    }

    public function addProduct(array $validatedData): void
    {
        // Check if product already exists
        $existingProduct = $this->productRepository->findExisting(
            $validatedData['generic_name'],
            $validatedData['brand_name'],
            $validatedData['form'],
            $validatedData['strength']
        );

        if ($existingProduct && $existingProduct->is_archived) {
            throw new Exception('A similar product exists but is archived. Please unarchive it instead.');
        }

        if ($existingProduct && !$existingProduct->is_archived) {
            throw new Exception('A product with the same details already exists.');
        }

        $product = $this->productRepository->create($validatedData);

        HistoryLogHelper::logProductAction(
            'REGISTERED PRODUCT',
            "Registered a new product: {$product->generic_name} ({$product->brand_name} {$product->form} - {$product->strength})",
            ['product_id' => $product->id]
        );
    }

    public function updateProduct(int $productId, array $validatedData): void
    {
        $product = $this->productRepository->findById($productId);
        if (!$product) {
            throw new Exception('Product not found.');
        }

        $old = $product->only(['generic_name', 'brand_name', 'form', 'strength']);
        $this->productRepository->update($product, $validatedData);

        HistoryLogHelper::logProductAction(
            'PRODUCT UPDATED',
            "Updated the product details for {$old['generic_name']} {$old['brand_name']} into {$validatedData['generic_name']} {$validatedData['brand_name']} ({$validatedData['form']} - {$validatedData['strength']})",
            ['product_id' => $product->id]
        );
    }

    public function archiveProduct(int $productId): void
    {
        $product = $this->productRepository->findById($productId);
        if (!$product) {
            throw new Exception('Product not found.');
        }

        $this->productRepository->archive($product);
        $this->inventoryRepository->archiveByProduct($productId);

        HistoryLogHelper::logProductAction(
            'PRODUCT ARCHIVED',
            "{$product->generic_name} {$product->brand_name} ({$product->form} - {$product->strength}) has been archived and its corresponding stocks assigned to it.",
            ['product_id' => $product->id]
        );
    }

    public function unarchiveProduct(int $productId): void
    {
        $product = $this->productRepository->findById($productId);
        if (!$product) {
            throw new Exception('Product not found.');
        }

        $this->productRepository->unarchive($product);
        $this->inventoryRepository->unarchiveByProduct($productId);

        HistoryLogHelper::logProductAction(
            'PRODUCT UNARCHIVED',
            "{$product->generic_name} {$product->brand_name} ({$product->form} - {$product->strength}) has been unarchived and its corresponding stocks assigned to it.",
            ['product_id' => $product->id]
        );
    }

    public function addStock(array $validatedData): void
    {
        $product = $this->productRepository->findById($validatedData['product_id']);

        // Check if existing stock batch
        $existingStock = $this->inventoryRepository->findExistingStock(
            $validatedData['product_id'],
            $validatedData['batch_number'],
            $validatedData['expiry_date'],
            $validatedData['branch_id']
        );

        if ($existingStock) {
            // Add to existing batch
            $oldQuantity = $existingStock->quantity;
            $existingStock->quantity += $validatedData['quantity'];
            $existingStock->save();

            // Log product movement
            $this->productMovementRepository->create([
                'product_id' => $existingStock->product_id,
                'inventory_id' => $existingStock->id,
                'user_id' => auth()->id(),
                'type' => 'IN',
                'quantity' => $validatedData['quantity'],
                'quantity_before' => $oldQuantity,
                'quantity_after' => $existingStock->quantity,
                'description' => 'Manual stock addition (existing batch)',
            ]);

            // Log action
            $formattedOldQty = number_format($oldQuantity);
            $formattedAddedQty = number_format($validatedData['quantity']);
            $formattedNewQty = number_format($existingStock->quantity);

            HistoryLogHelper::logInventoryAction(
                'STOCK ADDED',
                "Added additional stock (+{$formattedAddedQty}) in batch no. {$existingStock->batch_number} (Product: {$product->generic_name} {$product->brand_name} [{$product->form} - {$product->strength}]). From {$formattedOldQty} to {$formattedNewQty}.",
                [
                    'inventory_id' => $existingStock->id,
                    'product_id' => $existingStock->product_id,
                ]
            );
        } else {
            // Create new batch
            $inventory = $this->inventoryRepository->create([
                'product_id' => $validatedData['product_id'],
                'branch_id' => $validatedData['branch_id'],
                'batch_number' => $validatedData['batch_number'],
                'quantity' => $validatedData['quantity'],
                'expiry_date' => $validatedData['expiry_date'],
                'is_archived' => 0,
            ]);

            // Log product movement
            $this->productMovementRepository->create([
                'product_id' => $inventory->product_id,
                'inventory_id' => $inventory->id,
                'user_id' => auth()->id(),
                'type' => 'IN',
                'quantity' => $inventory->quantity,
                'quantity_before' => 0,
                'quantity_after' => $inventory->quantity,
                'description' => 'Manual stock addition (new batch)',
            ]);

            // Log action
            $formattedQty = number_format($inventory->quantity);
            $expiryFormatted = Carbon::parse($inventory->expiry_date)->translatedFormat('M d, Y');

            HistoryLogHelper::logInventoryAction(
                'STOCK ADDED',
                "Created a new batch for {$product->generic_name} {$product->brand_name} ({$product->form} - {$product->strength}). Batch No. {$inventory->batch_number} with a qty of {$formattedQty}. Expires in: {$expiryFormatted}.",
                [
                    'inventory_id' => $inventory->id,
                    'product_id' => $inventory->product_id,
                ]
            );
        }
    }

    public function editStock(int $inventoryId, array $validatedData): void
    {
        $inventory = $this->inventoryRepository->findByIdWithProduct($inventoryId);
        if (!$inventory) {
            throw new Exception('Stock not found.');
        }

        $old = $inventory->only(['batch_number', 'quantity', 'expiry_date']);

        $this->inventoryRepository->update($inventory, [
            'batch_number' => $validatedData['batch_number'],
            'quantity' => $validatedData['quantity'],
            'expiry_date' => $validatedData['expiry_date'],
        ]);

        // Log product movement if quantity changed
        $quantityChange = $validatedData['quantity'] - $old['quantity'];
        if ($quantityChange != 0) {
            $movementType = $quantityChange > 0 ? 'IN' : 'OUT';
            $description = $quantityChange > 0 ? 'Manual stock adjustment (add)' : 'Manual stock adjustment (remove)';

            $this->productMovementRepository->create([
                'product_id' => $inventory->product_id,
                'inventory_id' => $inventory->id,
                'user_id' => auth()->id(),
                'type' => $movementType,
                'quantity' => abs($quantityChange),
                'quantity_before' => $old['quantity'],
                'quantity_after' => $validatedData['quantity'],
                'description' => $description,
            ]);
        }

        // Log action
        $product = $inventory->product;
        $expiryFormatted = Carbon::parse($validatedData['expiry_date'])->translatedFormat('M d, Y');

        HistoryLogHelper::logInventoryAction(
            'STOCK UPDATED',
            "Updated the stock details from {$old['batch_number']} to {$validatedData['batch_number']} (Product: {$product->generic_name} {$product->brand_name} [{$product->form} - {$product->strength}]). From qty {$old['quantity']} to {$validatedData['quantity']}. Now expires in: {$expiryFormatted}.",
            [
                'inventory_id' => $inventory->id,
                'product_id' => $inventory->product_id,
            ]
        );
    }

    public function transferStock(int $inventoryId, int $quantity, int $destinationBranchId): void
    {
        $inventory = $this->inventoryRepository->findByIdWithProduct($inventoryId);
        if (!$inventory) {
            throw new Exception('Stock not found.');
        }

        if ($inventory->quantity < $quantity) {
            throw new Exception('Not enough stock to transfer.');
        }

        $transferResult = $this->inventoryRepository->transferStock($inventory, $quantity, $destinationBranchId);

        $sourceBranchName = $inventory->branch_id == 1 ? 'RHU 1' : 'RHU 2';
        $destBranchName = $destinationBranchId == 1 ? 'RHU 1' : 'RHU 2';

        // Log source movement
        $this->productMovementRepository->create([
            'product_id' => $inventory->product_id,
            'inventory_id' => $inventory->id,
            'user_id' => auth()->id(),
            'type' => 'OUT',
            'quantity' => $quantity,
            'quantity_before' => $inventory->quantity + $quantity,
            'quantity_after' => $inventory->quantity,
            'description' => "Stock transfer from {$sourceBranchName} to {$destBranchName}.",
        ]);

        // Log destination movement
        $this->productMovementRepository->create([
            'product_id' => $inventory->product_id,
            'inventory_id' => $transferResult['destination_inventory']->id,
            'user_id' => auth()->id(),
            'type' => 'IN',
            'quantity' => $quantity,
            'quantity_before' => $transferResult['old_quantity'],
            'quantity_after' => $transferResult['destination_inventory']->quantity,
            'description' => "Stock received from {$sourceBranchName} to {$destBranchName}.",
        ]);

        // No explicit history log for transfer as it's tracked via product movements
    }

    public function validateStockAvailability(array $medications): void
    {
        foreach ($medications as $med) {
            $inventory = $this->inventoryRepository->findById($med['inventory_id']);
            if (!$inventory) {
                throw new Exception('Invalid medicine selected.');
            }

            if ($inventory->quantity < $med['quantity']) {
                $product = $this->productRepository->findById($inventory->product_id);
                throw new Exception("Insufficient quantity for {$product->generic_name}. Available: {$inventory->quantity}");
            }
        }
    }
}
