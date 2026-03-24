<?php

namespace Tests\Unit\Repositories;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Repositories\Eloquent\NotificationPreferenceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationPreferenceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationPreferenceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new NotificationPreferenceRepository(new NotificationPreference());
    }

    public function test_upsert_user_preferences_creates_rows_with_defaults(): void
    {
        $user = User::factory()->create();

        $this->repository->upsertUserPreferences($user->id, ['low_stock', 'approval_needed'], [
            'low_stock' => ['email_enabled' => true, 'in_app_enabled' => false],
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type' => 'low_stock',
            'email_enabled' => 1,
            'in_app_enabled' => 0,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type' => 'approval_needed',
            'email_enabled' => 0,
            'in_app_enabled' => 1,
        ]);
    }

    public function test_upsert_user_preferences_updates_existing_rows(): void
    {
        $user = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id,
            'type' => 'low_stock',
            'email_enabled' => false,
            'in_app_enabled' => true,
        ]);

        $this->repository->upsertUserPreferences($user->id, ['low_stock'], [
            'low_stock' => ['email_enabled' => true, 'in_app_enabled' => false],
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type' => 'low_stock',
            'email_enabled' => 1,
            'in_app_enabled' => 0,
        ]);
    }

    public function test_upsert_user_preferences_uses_single_bulk_statement(): void
    {
        $user = User::factory()->create();

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        $this->repository->upsertUserPreferences($user->id, ['low_stock', 'approval_needed', 'hold_expiry'], [
            'low_stock' => ['email_enabled' => true, 'in_app_enabled' => true],
            'approval_needed' => ['email_enabled' => false, 'in_app_enabled' => true],
            'hold_expiry' => ['email_enabled' => true, 'in_app_enabled' => false],
        ]);

        $queryLog = collect(DB::connection()->getQueryLog());
        $preferenceQueries = $queryLog->filter(
            fn (array $query) => str_contains(strtolower($query['query']), 'notification_preferences')
        );

        $this->assertCount(1, $preferenceQueries);
        $this->assertTrue(
            str_contains(strtolower($preferenceQueries->first()['query']), 'insert'),
            'Expected a single bulk insert/upsert statement for notification_preferences.'
        );
    }
}

