<?php

namespace Tests\Feature\Tenancy;

use App\Mail\TenantInvitationMail;
use App\Models\Barangay;
use App\Models\Province;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\TenantInvitationService;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class TenantAuthLinkGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_notification_uses_tenant_route_from_session(): void
    {
        $province = Province::factory()->create(['slug' => 'bulacan']);
        $barangay = Barangay::factory()->create([
            'province_id' => $province->id,
            'slug' => 'malolos',
        ]);

        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        session([
            'tenant.route_slug_province' => $province->slug,
            'tenant.route_slug_barangay' => $barangay->slug,
        ]);

        $notification = new ResetPassword($token);
        $mailMessage = $notification->toMail($user);

        $this->assertStringContainsString('/bulacan/malolos/reset-password', (string) $mailMessage->actionUrl);
    }

    public function test_verify_email_notification_uses_tenant_route_from_session(): void
    {
        $province = Province::factory()->create(['slug' => 'bulacan']);
        $barangay = Barangay::factory()->create([
            'province_id' => $province->id,
            'slug' => 'malolos',
        ]);

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        session([
            'tenant.route_slug_province' => $province->slug,
            'tenant.route_slug_barangay' => $barangay->slug,
        ]);

        $notification = new VerifyEmail();
        $mailMessage = $notification->toMail($user);

        $this->assertStringContainsString('/bulacan/malolos/verify-email/', (string) $mailMessage->actionUrl);
    }

    public function test_invitation_email_contains_tenant_acceptance_link(): void
    {
        Mail::fake();

        $province = Province::factory()->create(['slug' => 'bulacan']);
        $barangay = Barangay::factory()->create([
            'province_id' => $province->id,
            'slug' => 'malolos',
        ]);

        $role = TenantRole::create([
            'name' => 'Barangay Administrator',
            'slug' => 'barangay-admin',
            'scope_type' => 'barangay',
            'is_system_role' => true,
        ]);

        $inviter = User::factory()->create();
        $this->be($inviter);

        $service = app(TenantInvitationService::class);
        $ctx = TenantContext::forBarangay($province, $barangay);
        $service->create($ctx, 'invitee@example.com', $role->id);

        Mail::assertSent(TenantInvitationMail::class, function (TenantInvitationMail $mail) {
            return str_contains($mail->invitationUrl, '/bulacan/malolos/invite/accept/');
        });
    }
}

