<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Repositories\Interfaces\RepositoryInterface;
use App\Repositories\Interfaces\SupplierRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\HoldRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\AuditEventRepositoryInterface;
use App\Repositories\Interfaces\DashboardRepositoryInterface;
use App\Repositories\Interfaces\InventoryAdminRepositoryInterface;
use App\Repositories\Interfaces\ManageAccountRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Interfaces\NotificationPreferenceRepositoryInterface;
use App\Repositories\Interfaces\RolePermissionRepositoryInterface;
use App\Repositories\Interfaces\HistoryLogRepositoryInterface;
use App\Repositories\Interfaces\ProductMovementRepositoryInterface;
use App\Repositories\Interfaces\PatientRecordsRepositoryInterface;

use App\Repositories\Eloquent\SupplierRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\HoldRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\AuditEventRepository;
use App\Repositories\Eloquent\DashboardRepository;
use App\Repositories\Eloquent\InventoryAdminRepository;
use App\Repositories\Eloquent\ManageAccountRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\NotificationPreferenceRepository;
use App\Repositories\Eloquent\RolePermissionRepository;
use App\Repositories\Eloquent\HistoryLogRepository;
use App\Repositories\Eloquent\ProductMovementRepository;
use App\Repositories\Eloquent\PatientRecordsRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All repository interface-to-implementation bindings.
     *
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        SupplierRepositoryInterface::class   => SupplierRepository::class,
        ProductRepositoryInterface::class    => ProductRepository::class,
        HoldRepositoryInterface::class       => HoldRepository::class,
        OrderRepositoryInterface::class      => OrderRepository::class,
        AuditEventRepositoryInterface::class => AuditEventRepository::class,
        DashboardRepositoryInterface::class => DashboardRepository::class,
        InventoryAdminRepositoryInterface::class => InventoryAdminRepository::class,
        ManageAccountRepositoryInterface::class => ManageAccountRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        NotificationPreferenceRepositoryInterface::class => NotificationPreferenceRepository::class,
        RolePermissionRepositoryInterface::class => RolePermissionRepository::class,
        HistoryLogRepositoryInterface::class => HistoryLogRepository::class,
        ProductMovementRepositoryInterface::class => ProductMovementRepository::class,
        PatientRecordsRepositoryInterface::class => PatientRecordsRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    public function boot(): void
    {
        //
    }
}
