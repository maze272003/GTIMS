<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\UserLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProvinceBarangaySeeder::class,
        ]);

        if (Branch::query()->count() === 0) {
            $this->call([BranchSeeder::class]);
        }

        if (UserLevel::query()->count() === 0) {
            $this->call([UserLevelSeeder::class]);
        }

        if (Permission::query()->count() === 0) {
            $this->call([PermissionSeeder::class]);
        }

        if (Product::query()->count() === 0) {
            $this->call([ProductSeeder::class]);
        }

        if (Inventory::query()->count() === 0) {
            $this->call([InventorySeeder::class]);
        }

        $this->call([
            ModeratorSeeder::class,
            SuperAdminSeeder::class,
            AdminUserSeeder::class,
            TenantDemoDataSeeder::class,
            LowStockSettingSeeder::class,
        ]);

        $chunk = max(100, (int) config('tenancy.seeder.chunk_size', 500));

        Artisan::call('tenant:migration', [
            'action' => 'backfill',
            '--chunk' => $chunk,
        ]);
        $this->command?->line(trim((string) Artisan::output()));

        Artisan::call('tenant:sync-rbac');
        $this->command?->line(trim((string) Artisan::output()));

        Artisan::call('tenant:validate-slugs');
        $this->command?->line(trim((string) Artisan::output()));
    }
}

