<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\StaffInvitation;
use App\Notifications\StaffOtpIssued;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StaffInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // HandleInertiaRequests calls $user->hasPermissionTo('approvals.view') on
        // every page render, which throws (not just returns false) if that
        // permission isn't registered — seed the real permission set, as the
        // app always has in practice.
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createInvitedStaff(): User
    {
        Notification::fake();

        return app(UserService::class)->create([
            'name'  => 'Jane Teller',
            'email' => 'jane@example.com',
            'phone' => null,
            'transaction_limit' => 5000,
        ]);
    }

    public function test_creating_a_staff_member_invites_rather_than_activates(): void
    {
        Notification::fake();

        $user = app(UserService::class)->create([
            'name'  => 'Jane Teller',
            'email' => 'jane@example.com',
            'phone' => null,
            'transaction_limit' => 5000,
        ]);

        $this->assertEquals('invited', $user->status);
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->invitation_accepted_at);
        $this->assertFalse($user->isActive());

        Notification::assertSentTo($user, StaffInvitation::class);
    }

    public function test_invited_user_cannot_log_in_before_accepting(): void
    {
        $user = $this->createInvitedStaff();

        // Their password is an unknown random hash — no credential guess should work.
        $response = $this->post('/login', [
            'staff_id' => $user->staff_id,
            'password' => 'anything-guessable',
        ]);

        $response->assertSessionHasErrors('staff_id');
        $this->assertGuest();
    }

    public function test_accepting_the_invitation_issues_an_otp_and_activates_the_account(): void
    {
        Notification::fake();
        $user = $this->createInvitedStaff();

        $acceptUrl = URL::temporarySignedRoute('staff-invitation.show', now()->addDays(7), ['user' => $user->id]);

        // GET shows the accept screen, not-yet-done.
        $show = $this->get($acceptUrl);
        $show->assertOk();
        $show->assertInertia(fn ($page) => $page->where('already_done', false));

        // POST accepts it.
        $this->post($acceptUrl)->assertOk();

        $user->refresh();
        $this->assertEquals('active', $user->status);
        $this->assertTrue($user->must_change_password);
        $this->assertNotNull($user->invitation_accepted_at);

        Notification::assertSentTo($user, StaffOtpIssued::class);
    }

    public function test_accept_link_cannot_be_reused(): void
    {
        Notification::fake();
        $user = $this->createInvitedStaff();

        $acceptUrl = URL::temporarySignedRoute('staff-invitation.show', now()->addDays(7), ['user' => $user->id]);

        $this->post($acceptUrl);
        Notification::assertSentToTimes($user, StaffOtpIssued::class, 1);

        // Second visit/accept must not re-issue a new OTP.
        $second = $this->post($acceptUrl);
        $second->assertInertia(fn ($page) => $page->where('already_done', true));
        Notification::assertSentToTimes($user, StaffOtpIssued::class, 1);
    }

    public function test_must_change_password_blocks_every_admin_route_except_the_change_screen(): void
    {
        $user = $this->createInvitedStaff();
        $user->forceFill(['status' => 'active', 'must_change_password' => true, 'two_factor_enabled' => false])->save();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.password.change'));

        // The change-password screen itself must remain reachable.
        $this->actingAs($user)
            ->get(route('admin.password.change'))
            ->assertOk();
    }

    public function test_completing_the_forced_change_clears_the_gate(): void
    {
        $user = $this->createInvitedStaff();
        $user->forceFill(['status' => 'active', 'must_change_password' => true, 'two_factor_enabled' => false])->save();

        $this->actingAs($user)
            ->post(route('admin.password.change.store'), [
                'password' => 'NewStrongPass1!',
                'password_confirmation' => 'NewStrongPass1!',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);

        $this->actingAs($user->fresh())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_manual_password_reset_also_forces_a_change(): void
    {
        $admin = $this->createInvitedStaff();
        $admin->forceFill(['status' => 'active', 'must_change_password' => false])->save();

        app(UserService::class)->resetPassword($admin);

        $this->assertTrue($admin->fresh()->must_change_password);
    }
}
