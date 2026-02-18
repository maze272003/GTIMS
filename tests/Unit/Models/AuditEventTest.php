<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuditEventTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $level = UserLevel::create(['name' => 'admin']);
        $branch = Branch::create(['name' => 'RHU 1']);
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_audit_event_is_immutable_no_update()
    {
        $event = AuditEvent::create([
            'action' => 'test.action',
            'entity_type' => 'test',
            'entity_id' => 1,
            'user_id' => $this->user->id,
            'before' => ['old' => 'value'],
            'after' => ['new' => 'value'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Audit events are immutable');
        $event->update(['action' => 'changed']);
    }

    public function test_audit_event_is_immutable_no_delete()
    {
        $event = AuditEvent::create([
            'action' => 'test.action',
            'entity_type' => 'test',
            'entity_id' => 1,
            'user_id' => $this->user->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Audit events are immutable');
        $event->delete();
    }

    public function test_audit_event_casts_json_fields()
    {
        $event = AuditEvent::create([
            'action' => 'test.action',
            'entity_type' => 'test',
            'entity_id' => 1,
            'user_id' => $this->user->id,
            'before' => ['key' => 'old'],
            'after' => ['key' => 'new'],
            'metadata' => ['ip' => '127.0.0.1'],
        ]);

        $event = $event->fresh();
        $this->assertIsArray($event->before);
        $this->assertIsArray($event->after);
        $this->assertIsArray($event->metadata);
        $this->assertEquals('old', $event->before['key']);
    }
}
