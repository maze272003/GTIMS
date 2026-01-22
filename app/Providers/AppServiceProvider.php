<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

// Repositories
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ProductMovementRepositoryInterface;
use App\Repositories\Contracts\HistoryLogRepositoryInterface;
use App\Repositories\Eloquent\InventoryRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\ProductMovementRepository;
use App\Repositories\Eloquent\HistoryLogRepository;

// Services
use App\Services\Contracts\InventoryServiceInterface;
use App\Services\Implementations\InventoryService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Repositories
        $this->app->bind(InventoryRepositoryInterface::class, InventoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(ProductMovementRepositoryInterface::class, ProductMovementRepository::class);
        $this->app->bind(HistoryLogRepositoryInterface::class, HistoryLogRepository::class);

        // Bind Services
        $this->app->bind(InventoryServiceInterface::class, InventoryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS only in production
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
