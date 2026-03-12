<?php

namespace Tests\Feature\Admin;

use App\Exports\PatientRecordsExport;
use App\Models\Barangay;
use App\Models\Branch;
use App\Models\Patientrecords;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PatientRecordsFilteringTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchOne;
    private Branch $branchTwo;
    private Barangay $barangayOne;
    private Barangay $barangayTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchOne = Branch::create([
            'name' => 'RHU Branch One',
            'code' => 'rhu-branch-one',
            'is_archived' => false,
        ]);

        $this->branchTwo = Branch::create([
            'name' => 'RHU Branch Two',
            'code' => 'rhu-branch-two',
            'is_archived' => false,
        ]);

        $this->barangayOne = Barangay::create(['barangay_name' => 'San Isidro']);
        $this->barangayTwo = Barangay::create(['barangay_name' => 'Poblacion']);
    }

    public function test_global_search_matches_resident_and_location_fields(): void
    {
        $user = $this->createUserWithPermissions(['patients.view', 'patients.manage', 'reports.export'], $this->branchOne->id);

        Patientrecords::create([
            'patient_name' => 'Juan Dela Cruz',
            'barangay_id' => $this->barangayOne->id,
            'purok' => 'Purok 1',
            'category' => 'Adult',
            'date_dispensed' => '2026-02-15',
            'branch_id' => $this->branchOne->id,
        ]);

        Patientrecords::create([
            'patient_name' => 'Maria Santos',
            'barangay_id' => $this->barangayTwo->id,
            'purok' => 'Purok 9',
            'category' => 'Senior',
            'date_dispensed' => '2026-02-16',
            'branch_id' => $this->branchTwo->id,
        ]);

        $nameSearchResponse = $this->actingAs($user)->get(route('admin.patientrecords', [
            'search' => 'Juan',
        ]));

        $nameSearchResponse->assertOk();
        $nameSearchResponse->assertSee('Juan Dela Cruz');
        $nameSearchResponse->assertDontSee('Maria Santos');

        $locationSearchResponse = $this->actingAs($user)->get(route('admin.patientrecords', [
            'search' => 'Poblacion',
        ]));

        $locationSearchResponse->assertOk();
        $locationSearchResponse->assertSee('Maria Santos');
        $locationSearchResponse->assertDontSee('Juan Dela Cruz');
    }

    public function test_combined_filters_apply_branch_category_barangay_and_date_dispensed(): void
    {
        $user = $this->createUserWithPermissions(['patients.view', 'patients.manage', 'reports.export'], $this->branchOne->id);

        Patientrecords::create([
            'patient_name' => 'Filter Match Resident',
            'barangay_id' => $this->barangayOne->id,
            'purok' => 'Purok 3',
            'category' => 'Adult',
            'date_dispensed' => '2026-02-10',
            'branch_id' => $this->branchOne->id,
        ]);

        Patientrecords::create([
            'patient_name' => 'Wrong Category Resident',
            'barangay_id' => $this->barangayOne->id,
            'purok' => 'Purok 3',
            'category' => 'Child',
            'date_dispensed' => '2026-02-10',
            'branch_id' => $this->branchOne->id,
        ]);

        Patientrecords::create([
            'patient_name' => 'Wrong Branch Resident',
            'barangay_id' => $this->barangayOne->id,
            'purok' => 'Purok 3',
            'category' => 'Adult',
            'date_dispensed' => '2026-02-10',
            'branch_id' => $this->branchTwo->id,
        ]);

        Patientrecords::create([
            'patient_name' => 'Out Of Range Resident',
            'barangay_id' => $this->barangayOne->id,
            'purok' => 'Purok 3',
            'category' => 'Adult',
            'date_dispensed' => '2026-01-01',
            'branch_id' => $this->branchOne->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.patientrecords', [
            'branch_filter' => $this->branchOne->id,
            'category' => 'Adult',
            'barangay_id' => $this->barangayOne->id,
            'from_date' => '2026-02-01',
            'to_date' => '2026-02-28',
        ]));

        $response->assertOk();
        $response->assertSee('Filter Match Resident');
        $response->assertDontSee('Wrong Category Resident');
        $response->assertDontSee('Wrong Branch Resident');
        $response->assertDontSee('Out Of Range Resident');
    }

    public function test_non_manager_cannot_override_branch_scope_with_branch_filter(): void
    {
        $user = $this->createUserWithPermissions(['patients.view'], $this->branchOne->id);

        Patientrecords::create([
            'patient_name' => 'Own Branch Resident',
            'barangay_id' => $this->barangayOne->id,
            'purok' => 'Purok 1',
            'category' => 'Adult',
            'date_dispensed' => '2026-02-20',
            'branch_id' => $this->branchOne->id,
        ]);

        Patientrecords::create([
            'patient_name' => 'Other Branch Resident',
            'barangay_id' => $this->barangayTwo->id,
            'purok' => 'Purok 2',
            'category' => 'Adult',
            'date_dispensed' => '2026-02-20',
            'branch_id' => $this->branchTwo->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.patientrecords', [
            'branch_filter' => $this->branchTwo->id,
        ]));

        $response->assertOk();
        $response->assertSee('Own Branch Resident');
        $response->assertDontSee('Other Branch Resident');
    }

    public function test_excel_export_uses_the_same_filtered_query_logic(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $user = $this->createUserWithPermissions(['patients.view', 'patients.manage', 'reports.export'], $this->branchOne->id);

        $expected = Patientrecords::create([
            'patient_name' => 'Export Match Resident',
            'barangay_id' => $this->barangayOne->id,
            'purok' => 'Purok 7',
            'category' => 'Adult',
            'date_dispensed' => '2026-02-11',
            'branch_id' => $this->branchOne->id,
        ]);

        $excluded = Patientrecords::create([
            'patient_name' => 'Export Excluded Resident',
            'barangay_id' => $this->barangayTwo->id,
            'purok' => 'Purok 8',
            'category' => 'Child',
            'date_dispensed' => '2026-02-11',
            'branch_id' => $this->branchTwo->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.patientrecords.exportExcel', [
            'search' => 'Export Match',
            'branch_filter' => $this->branchOne->id,
            'category' => 'Adult',
            'barangay_id' => $this->barangayOne->id,
            'from_date' => '2026-02-01',
            'to_date' => '2026-02-28',
        ]));

        $response->assertOk();

        Excel::assertDownloaded('/^patient_records_.*\\.xlsx$/', function (PatientRecordsExport $export) use ($expected, $excluded) {
            $recordIds = $export->query()->pluck('id')->all();

            return in_array($expected->id, $recordIds, true)
                && !in_array($excluded->id, $recordIds, true)
                && count($recordIds) === 1;
        });
    }

    private function createUserWithPermissions(array $permissionNames, int $branchId): User
    {
        $level = UserLevel::create([
            'name' => 'test-level-' . uniqid(),
        ]);

        $permissionIds = [];
        foreach ($permissionNames as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['group' => 'Patients', 'description' => $permissionName]
            );
            $permissionIds[] = $permission->id;
        }

        $level->permissions()->sync($permissionIds);

        return User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branchId,
        ]);
    }
}
