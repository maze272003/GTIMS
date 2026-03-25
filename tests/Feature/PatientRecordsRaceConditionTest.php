<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use App\Models\UserLevel;
use App\Repositories\Eloquent\PatientRecordsRepository;
use App\Repositories\Interfaces\PatientRecordsRepositoryInterface;
use App\Services\PatientRecordsAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class PatientRecordsRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Barangay $barangay;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'name' => 'RHU 1',
            'code' => 'rhu-1',
            'is_archived' => false,
        ]);

        $this->barangay = Barangay::create([
            'barangay_name' => 'San Isidro',
        ]);

        $this->user = $this->createAuthorizedUser([
            'patients.manage',
        ]);
    }

    public function test_concurrent_inventory_deductions_prevent_overselling(): void
    {
        $inventory = $this->createInventory(100);

        $firstResponse = $this->actingAs($this->user)->post(
            route('admin.patientrecords.adddispensation'),
            $this->dispensationPayload($inventory, 60, 'First Patient')
        );

        $firstResponse->assertRedirect(route('admin.patientrecords'));
        $firstResponse->assertSessionHas('success');

        $secondResponse = $this->actingAs($this->user)->post(
            route('admin.patientrecords.adddispensation'),
            $this->dispensationPayload($inventory, 60, 'Second Patient')
        );

        $secondResponse->assertSessionHasErrorsIn('adddispensation', ['medications']);

        $inventory->refresh();

        $this->assertSame(40, (int) $inventory->onhand_qty);
        $this->assertSame(40, (int) $inventory->quantity);
        $this->assertDatabaseCount('patientrecords', 1);
        $this->assertDatabaseCount('dispensedmedications', 1);
        $this->assertDatabaseCount('product_movements', 1);
    }

    public function test_transaction_rollback_on_failure(): void
    {
        $inventory = $this->createInventory(100);

        $this->app->instance(PatientRecordsRepositoryInterface::class, new class extends PatientRecordsRepository
        {
            public function createDispensedMedication(array $data): void
            {
                throw new RuntimeException('Simulated medication persistence failure.');
            }
        });

        $response = $this->actingAs($this->user)->post(
            route('admin.patientrecords.adddispensation'),
            $this->dispensationPayload($inventory, 20)
        );

        $response->assertSessionHasErrorsIn('adddispensation', ['medications']);

        $inventory->refresh();

        $this->assertSame(100, (int) $inventory->onhand_qty);
        $this->assertSame(100, (int) $inventory->quantity);
        $this->assertDatabaseCount('patientrecords', 0);
        $this->assertDatabaseCount('history_logs', 0);
        $this->assertDatabaseCount('dispensedmedications', 0);
        $this->assertDatabaseCount('product_movements', 0);
    }

    public function test_validation_catches_invalid_quantities(): void
    {
        $inventory = $this->createInventory(100);

        $zeroQuantity = $this->actingAs($this->user)->post(
            route('admin.patientrecords.adddispensation'),
            $this->dispensationPayload($inventory, 0)
        );
        $zeroQuantity->assertSessionHasErrorsIn('adddispensation', ['medications.0.quantity']);

        $negativeQuantity = $this->actingAs($this->user)->post(
            route('admin.patientrecords.adddispensation'),
            $this->dispensationPayload($inventory, -1)
        );
        $negativeQuantity->assertSessionHasErrorsIn('adddispensation', ['medications.0.quantity']);

        $exceedsMaximum = $this->actingAs($this->user)->post(
            route('admin.patientrecords.adddispensation'),
            $this->dispensationPayload($inventory, 10000)
        );
        $exceedsMaximum->assertSessionHasErrorsIn('adddispensation', ['medications.0.quantity']);

        $exceedsStock = $this->actingAs($this->user)->post(
            route('admin.patientrecords.adddispensation'),
            $this->dispensationPayload($inventory, 101)
        );
        $exceedsStock->assertSessionHasErrorsIn('adddispensation', ['medications']);

        $inventory->refresh();

        $this->assertSame(100, (int) $inventory->onhand_qty);
        $this->assertSame(100, (int) $inventory->quantity);
        $this->assertDatabaseCount('patientrecords', 0);
    }

    public function test_type_conversion_helpers_validate_numeric_values(): void
    {
        $service = $this->app->make(PatientRecordsAdminService::class);

        $safeIntCast = new ReflectionMethod($service, 'safeIntCast');
        $safeIntCast->setAccessible(true);
        $safeFloatCast = new ReflectionMethod($service, 'safeFloatCast');
        $safeFloatCast->setAccessible(true);

        $this->assertSame(15, $safeIntCast->invoke($service, '15'));
        $this->assertSame(15.5, $safeFloatCast->invoke($service, '15.5'));
        $this->assertSame(0.0, $safeFloatCast->invoke($service, null));

        $this->expectException(RuntimeException::class);
        $safeIntCast->invoke($service, 'ABC123');
    }

    private function createAuthorizedUser(array $permissionNames): User
    {
        $level = UserLevel::create([
            'name' => 'admin',
        ]);

        $level->permissions()->sync(
            collect($permissionNames)
                ->map(fn (string $name): int => Permission::firstOrCreate(
                    ['name' => $name],
                    ['group' => 'Patients', 'description' => $name]
                )->id)
                ->all()
        );

        return User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    private function createInventory(int $quantity): Inventory
    {
        $product = Product::factory()->create([
            'generic_name' => 'Amoxicillin',
            'brand_name' => 'Brand A',
            'form' => 'Capsule',
            'strength' => '500mg',
            'is_archived' => false,
        ]);

        return Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $this->branch->id,
            'batch_number' => 'BATCH-001',
            'quantity' => $quantity,
            'onhand_qty' => $quantity,
            'hold_qty' => 0,
            'expiry_date' => now()->addMonth()->toDateString(),
            'is_archived' => false,
        ]);
    }

    private function dispensationPayload(Inventory $inventory, int $quantity, string $patientName = 'Juan Dela Cruz'): array
    {
        return [
            'patient-name' => $patientName,
            'barangay_id' => $this->barangay->id,
            'purok' => 'Purok 1',
            'category' => 'Adult',
            'date-dispensed' => now()->toDateString(),
            'medications' => [
                [
                    'name' => $inventory->id,
                    'quantity' => $quantity,
                ],
            ],
        ];
    }
}
