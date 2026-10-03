<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DeviceSessionController;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The test environment defaults SESSION_DRIVER to 'array' (nothing
        // persisted) for speed; this suite specifically exercises the
        // database-backed session/device-management feature, so it needs
        // the real driver — the `sessions` table already exists via migrations.
        config(['session.driver' => 'database']);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeCustomer(): Customer
    {
        Notification::fake();

        return app(CustomerService::class)->create([
            'name'  => 'Amina Yusuf',
            'email' => 'amina@example.com',
            'phone' => '2520610000001',
        ]);
    }

    public function test_staff_session_is_tagged_with_the_web_guard(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['status' => 'active', 'must_change_password' => false, 'two_factor_enabled' => false])->save();

        $this->actingAs($user)->get(route('admin.dashboard'));

        $row = DB::table('sessions')->where('user_id', $user->id)->where('guard', 'web')->first();
        $this->assertNotNull($row);
    }

    public function test_customer_session_is_tagged_with_the_customer_guard_not_left_null(): void
    {
        $customer = $this->makeCustomer();
        $customer->forceFill(['status' => 'active', 'must_change_password' => false, 'two_factor_enabled' => false])->save();

        $this->actingAs($customer, 'customer')->get(route('customer.dashboard'));

        $row = DB::table('sessions')->where('user_id', $customer->id)->where('guard', 'customer')->first();
        $this->assertNotNull($row, 'Customer session must be tagged — Laravel\'s default session handler only stamps the default (web) guard.');
    }

    public function test_staff_can_see_their_own_active_session_and_log_out_another_device(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['status' => 'active', 'must_change_password' => false, 'two_factor_enabled' => false])->save();

        $this->actingAs($user)->get(route('admin.dashboard'));

        // Simulate a second device: another session row for the same user.
        DB::table('sessions')->insert([
            'id' => 'other-device-session-id',
            'user_id' => $user->id,
            'guard' => 'web',
            'ip_address' => '10.0.0.9',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/1.0',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.profile.sessions'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('sessions', 2));

        $this->actingAs($user)->delete(route('admin.profile.sessions.destroy', 'other-device-session-id'));

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session-id']);
    }

    // Exercised directly against the controller (rather than two sequential
    // HTTP round-trips) because each isolated `actingAs()->post()` call gets
    // its own fresh session id in this test harness — a real browser's
    // cookie stays stable across requests, but simulating that faithfully
    // here would test the test harness more than the actual logic. This
    // still calls the real controller, the real trait, and a real password
    // check — only the HTTP transport layer is skipped.
    public function test_logout_other_devices_requires_correct_password_and_keeps_current_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $user->forceFill(['status' => 'active', 'must_change_password' => false, 'two_factor_enabled' => false])->save();
        Auth::guard('web')->login($user);

        $this->startSession();
        $currentId = session()->getId();

        DB::table('sessions')->insert([
            ['id' => $currentId, 'user_id' => $user->id, 'guard' => 'web', 'ip_address' => '127.0.0.1', 'user_agent' => 'Test', 'payload' => base64_encode('x'), 'last_activity' => time()],
            ['id' => 'another-device', 'user_id' => $user->id, 'guard' => 'web', 'ip_address' => '10.0.0.9', 'user_agent' => 'Mozilla/5.0', 'payload' => base64_encode('x'), 'last_activity' => time()],
        ]);

        $controller = app(DeviceSessionController::class);

        // Wrong password: nothing is deleted.
        $wrongRequest = Request::create('/admin/profile/sessions/logout-others', 'POST', ['password' => 'wrong']);
        $wrongRequest->setLaravelSession(app('session.store'));
        $controller->destroyOthers($wrongRequest);
        $this->assertDatabaseHas('sessions', ['id' => 'another-device']);
        $this->assertDatabaseHas('sessions', ['id' => $currentId]);

        // Correct password: every other session goes, the current one stays.
        $rightRequest = Request::create('/admin/profile/sessions/logout-others', 'POST', ['password' => 'correct-password']);
        $rightRequest->setLaravelSession(app('session.store'));
        $controller->destroyOthers($rightRequest);
        $this->assertDatabaseMissing('sessions', ['id' => 'another-device']);
        $this->assertDatabaseHas('sessions', ['id' => $currentId]);
    }
}
