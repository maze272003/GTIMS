<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\LowStockSetting;
use App\Models\Patientrecords;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Tenant geo bootstrap (idempotent and safe to rerun).
        $this->call([
            ProvinceBarangaySeeder::class,
        ]);

        // Core legacy/module seeders (guarded to avoid duplicate bulk inserts on reruns).
        $this->seedIfEmpty(Branch::class, BranchSeeder::class);
        $this->seedIfEmpty(UserLevel::class, UserLevelSeeder::class);
        $this->seedIfEmpty(User::class, UserSeeder::class);
        $this->seedIfEmpty(Permission::class, PermissionSeeder::class);
        $this->seedIfEmpty(Product::class, ProductSeeder::class);
        $this->seedIfEmpty(Inventory::class, InventorySeeder::class);
        $this->seedIfEmpty(LowStockSetting::class, LowStockSettingSeeder::class);
        $this->seedIfEmpty(Patientrecords::class, PatientRecordsSeeder::class);
        if (!DB::table('notifications')->exists()) {
            $this->call([TransactionLogSeeder::class]);
        }

        // Tenancy demo/admin accounts and tenant-scoped sample data.
        $this->call([
            ModeratorSeeder::class,
            SuperAdminSeeder::class,
            AdminUserSeeder::class,
            TenantDemoDataSeeder::class,
        ]);

        $chunk = max(100, (int) config('tenancy.seeder.chunk_size', 500));

        $this->runArtisan('tenant:migration', [
            'action' => 'backfill',
            '--chunk' => $chunk,
        ]);
        $this->runArtisan('tenant:sync-rbac');
        $this->runArtisan('tenant:validate-slugs');
    }

    protected function seedIfEmpty(string $modelClass, string $seederClass): void
    {
        if ($modelClass::query()->exists()) {
            return;
        }

        $this->call([$seederClass]);
    }

    protected function runArtisan(string $command, array $arguments = []): void
    {
        Artisan::call($command, $arguments);
        $output = trim((string) Artisan::output());
        if ($output !== '') {
            $this->command?->line($output);
        }
    }
}
