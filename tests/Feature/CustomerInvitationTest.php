<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Notifications\CustomerInvitation;
use App\Notifications\CustomerOtpIssued;
use App\Services\CustomerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // HandleInertiaRequests checks staff permissions on every render; not
        // strictly needed for the customer guard, but harmless and consistent.
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createInvitedCustomer(): Customer
    {
        Notification::fake();

        return app(CustomerService::class)->create([
            'name'  => 'Amina Yusuf',
            'email' => 'amina@example.com',
            'phone' => '2520610000001',
        ]);
    }

    public function test_creating_a_customer_invites_rather_than_activates(): void
    {
        Notification::fake();

        $customer = app(CustomerService::class)->create([
            'name'  => 'Amina Yusuf',
            'email' => 'amina@example.com',
            'phone' => '2520610000001',
        ]);

        $this->assertEquals('pending', $customer->status);
        $this->assertTrue($customer->must_change_password);
        $this->assertNull($customer->invitation_accepted_at);
        $this->assertFalse($customer->isActive());

        Notification::assertSentTo($customer, CustomerInvitation::class);
    }

    public function test_invited_customer_cannot_log_in_before_accepting(): void
    {
        $customer = $this->createInvitedCustomer();

        $response = $this->post('/portal/login', [
            'email'    => $customer->email,
            'password' => 'anything-guessable',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }

    public function test_accepting_the_invitation_issues_an_otp(): void
    {
        Notification::fake();
        $customer = $this->createInvitedCustomer();

        $acceptUrl = URL::temporarySignedRoute('customer-invitation.show', now()->addDays(7), ['customer' => $customer->id]);

        $show = $this->get($acceptUrl);
        $show->assertOk();
        $show->assertInertia(fn ($page) => $page->where('already_done', false));

        $this->post($acceptUrl)->assertOk();

        $customer->refresh();
        $this->assertNotNull($customer->invitation_accepted_at);
        $this->assertTrue($customer->must_change_password);
        // Accepting doesn't bypass the separate KYC/active gate.
        $this->assertEquals('pending', $customer->status);

        Notification::assertSentTo($customer, CustomerOtpIssued::class);
    }

    public function test_accept_link_cannot_be_reused(): void
    {
        Notification::fake();
        $customer = $this->createInvitedCustomer();

        $acceptUrl = URL::temporarySignedRoute('customer-invitation.show', now()->addDays(7), ['customer' => $customer->id]);

        $this->post($acceptUrl);
        Notification::assertSentToTimes($customer, CustomerOtpIssued::class, 1);

        $second = $this->post($acceptUrl);
        $second->assertInertia(fn ($page) => $page->where('already_done', true));
        Notification::assertSentToTimes($customer, CustomerOtpIssued::class, 1);
    }

    public function test_must_change_password_blocks_every_portal_route_except_the_change_screen(): void
    {
        $customer = $this->createInvitedCustomer();
        $customer->forceFill(['status' => 'active', 'must_change_password' => true, 'two_factor_enabled' => false])->save();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertRedirect(route('customer.password.change'));

        $this->actingAs($customer, 'customer')
            ->get(route('customer.password.change'))
            ->assertOk();
    }

    public function test_completing_the_forced_change_clears_the_gate(): void
    {
        $customer = $this->createInvitedCustomer();
        $customer->forceFill(['status' => 'active', 'must_change_password' => true, 'two_factor_enabled' => false])->save();

        $this->actingAs($customer, 'customer')
            ->post(route('customer.password.change.store'), [
                'password' => 'NewStrongPass1!',
                'password_confirmation' => 'NewStrongPass1!',
            ])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertFalse($customer->fresh()->must_change_password);

        $this->actingAs($customer->fresh(), 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk();
    }

    public function test_manual_password_reset_also_forces_a_change(): void
    {
        $customer = $this->createInvitedCustomer();
        $customer->forceFill(['status' => 'active', 'must_change_password' => false])->save();

        app(CustomerService::class)->resetPassword($customer);

        $this->assertTrue($customer->fresh()->must_change_password);
    }
}
