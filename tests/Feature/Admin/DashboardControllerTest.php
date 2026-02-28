<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $level = UserLevel::create(['name' => 'Super Admin']);
        $branch = Branch::factory()->create([
            'name' => 'RHU 1',
            'code' => 'rhu-1',
            'is_archived' => false,
        ]);

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
        ]);

        $permission = Permission::firstOrCreate(['name' => 'dashboard.view']);
        RolePermission::firstOrCreate([
            'user_level_id' => $level->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_observability_ajax_update_returns_expected_payload(): void
    {
        $response = $this->actingAs($this->user)->get(
            route('admin.dashboard', [
                'ajax_update' => 'observability',
                'filter_timespan' => '30d',
                'grouping' => 'day',
            ]),
            [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]
        );

        $response->assertOk()->assertJsonStructure([
            'observability' => [
                'generated_at',
                'summary' => [
                    'operations_total',
                    'operations_per_hour',
                    'error_events',
                    'error_rate',
                    'avg_cycle_time_hours',
                    'avg_approval_time_hours',
                    'avg_fulfillment_time_hours',
                    'stale_open_requests',
                    'oldest_open_request_hours',
                    'pending_requests',
                    'today_movements',
                ],
                'throughput' => [
                    'labels',
                    'movements',
                    'requests',
                    'audit_events',
                    'combined',
                ],
                'latency' => [
                    'labels',
                    'approval_hours',
                    'fulfillment_hours',
                    'cycle_hours',
                ],
                'errors' => [
                    'labels',
                    'audit_failed',
                    'request_denied',
                    'history_failed',
                    'combined',
                    'top_categories',
                ],
                'bottlenecks' => [
                    'status_labels',
                    'status_counts',
                    'stale_open_requests',
                    'oldest_open_request_hours',
                    'top_aging_requests',
                ],
            ],
        ]);
    }

    public function test_main_charts_ajax_update_includes_observability_payload(): void
    {
        $response = $this->actingAs($this->user)->get(
            route('admin.dashboard', [
                'ajax_update' => 'main_charts',
                'filter_timespan' => '30d',
                'grouping' => 'day',
            ]),
            [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]
        );

        $response->assertOk()->assertJsonStructure([
            'consumptionLabels',
            'consumptionData',
            'topProducts' => [
                'labels',
                'data',
                'drilldown',
            ],
            'barangay' => [
                'labels',
                'stackedData',
            ],
            'patientVisit' => [
                'labels',
                'data',
            ],
            'observability' => [
                'generated_at',
                'summary',
                'throughput',
                'latency',
                'errors',
                'bottlenecks',
            ],
        ]);
    }
}
