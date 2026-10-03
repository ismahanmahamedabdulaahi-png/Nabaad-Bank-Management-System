<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// Somali stakeholder request: 5 wrong passwords in a row must lock the
// account out for 10 minutes, and every failed attempt before that must
// show how many attempts remain — not just a generic "wrong password"
// message. Covers both the staff and customer login flows, which each
// enforce this independently (see LoginRequest and the customer
// AuthenticatedSessionController).
class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_staff_wrong_password_shows_a_remaining_attempts_count_each_time(): void
    {
        $user = User::factory()->create(['staff_id' => 'STF-9001']);
        $user->forceFill(['status' => 'active'])->save();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $response = $this->post(route('login'), ['staff_id' => 'STF-9001', 'password' => 'wrong-password']);
            $response->assertSessionHasErrors('staff_id');
            $message = session('errors')->get('staff_id')[0];

            $expectedRemaining = 5 - $attempt;
            $this->assertStringContainsString("{$expectedRemaining} attempt", $message);
            $this->assertGuest();
        }
    }

    public function test_staff_is_locked_out_immediately_on_the_fifth_wrong_attempt_for_ten_minutes(): void
    {
        $user = User::factory()->create(['staff_id' => 'STF-9002']);
        $user->forceFill(['status' => 'active'])->save();

        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('login'), ['staff_id' => 'STF-9002', 'password' => 'wrong-password']);
        }

        // 5th wrong attempt must lock immediately — no 6th submission needed.
        $response = $this->post(route('login'), ['staff_id' => 'STF-9002', 'password' => 'wrong-password']);
        $message = session('errors')->get('staff_id')[0];

        $this->assertStringContainsStringIgnoringCase('locked', $message);
        $this->assertStringContainsString('10 minute', $message);
        $this->assertGuest();
    }

    public function test_a_correct_staff_password_before_the_limit_clears_the_lockout_counter(): void
    {
        $user = User::factory()->create(['staff_id' => 'STF-9003', 'password' => bcrypt('correct-password')]);
        $user->forceFill(['status' => 'active', 'must_change_password' => false, 'two_factor_enabled' => false])->save();

        $this->post(route('login'), ['staff_id' => 'STF-9003', 'password' => 'wrong-password']);
        $this->post(route('login'), ['staff_id' => 'STF-9003', 'password' => 'wrong-password']);

        $this->post(route('login'), ['staff_id' => 'STF-9003', 'password' => 'correct-password']);
        $this->assertAuthenticatedAs($user);
    }

    private function makeCustomer(): Customer
    {
        Notification::fake();

        $customer = app(CustomerService::class)->create([
            'name' => 'Amina Yusuf', 'email' => 'amina@example.com', 'phone' => '2520610000001',
        ]);
        $customer->forceFill(['status' => 'active'])->save();

        return $customer;
    }

    public function test_customer_wrong_password_shows_a_remaining_attempts_count_each_time(): void
    {
        $customer = $this->makeCustomer();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->post(route('customer.login'), ['email' => $customer->email, 'password' => 'wrong-password']);
            $message = session('errors')->get('email')[0];

            $expectedRemaining = 5 - $attempt;
            $this->assertStringContainsString("{$expectedRemaining} attempt", $message);
            $this->assertGuest('customer');
        }
    }

    public function test_customer_is_locked_out_immediately_on_the_fifth_wrong_attempt_for_ten_minutes(): void
    {
        $customer = $this->makeCustomer();

        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('customer.login'), ['email' => $customer->email, 'password' => 'wrong-password']);
        }

        $this->post(route('customer.login'), ['email' => $customer->email, 'password' => 'wrong-password']);
        $message = session('errors')->get('email')[0];

        $this->assertStringContainsStringIgnoringCase('locked', $message);
        $this->assertStringContainsString('10 minute', $message);
    }
}
