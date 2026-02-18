<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\IncomingRequest;
use App\Models\Branch;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IncomingRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_transition_to_valid_status()
    {
        $level = UserLevel::create(['name' => 'admin']);
        $branch = Branch::create(['name' => 'RHU 1']);
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $level->id,
            'branch_id' => $branch->id,
        ]);

        $request = IncomingRequest::create([
            'branch_id' => $branch->id,
            'requester_id' => $user->id,
            'priority' => 'normal',
            'status' => 'draft',
        ]);

        $this->assertTrue($request->canTransitionTo('requested'));
        $this->assertFalse($request->canTransitionTo('approved'));
        $this->assertFalse($request->canTransitionTo('fulfilled'));
    }
}
