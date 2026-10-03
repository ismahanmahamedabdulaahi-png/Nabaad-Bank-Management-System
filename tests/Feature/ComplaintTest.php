<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeCustomer(): Customer
    {
        Notification::fake();

        $customer = app(CustomerService::class)->create([
            'name' => 'Amina Yusuf', 'email' => 'amina@example.com', 'phone' => '2520610000001',
        ]);
        $customer->forceFill(['status' => 'active', 'must_change_password' => false, 'two_factor_enabled' => false])->save();

        return $customer;
    }

    public function test_a_customer_can_file_and_view_their_own_complaint(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->actingAs($customer, 'customer')->post(route('customer.complaints.store'), [
            'category'    => 'service_quality',
            'subject'     => 'Long queue at the branch',
            'description' => 'I waited over an hour to be served.',
        ]);

        $complaint = Complaint::first();
        $response->assertRedirect(route('customer.complaints.show', $complaint->id));
        $this->assertEquals($customer->id, $complaint->customer_id);
        $this->assertEquals('open', $complaint->status);
        $this->assertNotEmpty($complaint->complaint_number);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.complaints.show', $complaint->id))
            ->assertOk();
    }

    public function test_a_customer_cannot_view_another_customers_complaint(): void
    {
        $customerA = $this->makeCustomer();
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-TEST-0001', 'customer_id' => $customerA->id,
            'category' => 'other', 'subject' => 'x', 'description' => 'x', 'status' => 'open', 'priority' => 'medium',
        ]);

        $customerB = app(\App\Services\CustomerService::class)->create([
            'name' => 'Second Customer', 'email' => 'second@example.com', 'phone' => '2520610000002',
        ]);
        $customerB->forceFill(['status' => 'active', 'must_change_password' => false, 'two_factor_enabled' => false])->save();

        $this->actingAs($customerB, 'customer')
            ->get(route('customer.complaints.show', $complaint->id))
            ->assertForbidden();
    }

    public function test_staff_without_permission_cannot_view_complaints(): void
    {
        $customer = $this->makeCustomer();
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-TEST-0002', 'customer_id' => $customer->id,
            'category' => 'other', 'subject' => 'x', 'description' => 'x', 'status' => 'open', 'priority' => 'medium',
        ]);

        $loanOfficer = User::factory()->create();
        $loanOfficer->assignRole(Role::findByName('Loan Officer', 'web'));

        $this->actingAs($loanOfficer)
            ->get(route('admin.complaints.show', $complaint->id))
            ->assertForbidden();
    }

    public function test_a_customer_service_officer_can_resolve_a_complaint(): void
    {
        $customer = $this->makeCustomer();
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-TEST-0003', 'customer_id' => $customer->id,
            'category' => 'other', 'subject' => 'x', 'description' => 'x', 'status' => 'open', 'priority' => 'medium',
        ]);

        $cso = User::factory()->create();
        $cso->assignRole(Role::findByName('Customer Service Officer', 'web'));

        $this->actingAs($cso)->post(route('admin.complaints.status', $complaint->id), [
            'status' => 'resolved',
            'resolution_notes' => 'Refunded the fee.',
        ])->assertSessionHasNoErrors();

        $fresh = $complaint->fresh();
        $this->assertEquals('resolved', $fresh->status);
        $this->assertEquals('Refunded the fee.', $fresh->resolution_notes);
        $this->assertNotNull($fresh->resolved_at);
    }
}
