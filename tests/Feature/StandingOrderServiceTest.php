<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\StandingOrder;
use App\Models\User;
use App\Services\StandingOrderService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StandingOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;
    private StandingOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TST', 'status' => 'active']);
        $this->user   = User::factory()->create();
        $this->actingAs($this->user);

        $this->service = app(StandingOrderService::class);
    }

    private function makeAccount(float $balance): Account
    {
        static $seq = 0;
        $seq++;

        $customer = Customer::create([
            'customer_number' => "CUS-{$seq}",
            'name'            => "Customer {$seq}",
            'email'           => "customer{$seq}@example.com",
            'phone'           => "20000000{$seq}",
            'password'        => bcrypt('secret'),
            'status'          => 'active',
        ]);

        return Account::create([
            'account_number'  => "ACC-{$seq}",
            'customer_id'     => $customer->id,
            'branch_id'       => $this->branch->id,
            'account_type'    => 'savings',
            'status'          => 'active',
            'balance'         => $balance,
            'currency'        => 'USD',
            'opening_date'    => today(),
            'opened_by'       => $this->user->id,
            'minimum_balance' => 0,
        ]);
    }

    private function makeOrder(Account $from, Account $to, string $frequency = 'monthly', array $overrides = []): StandingOrder
    {
        return $this->service->create(array_merge([
            'source_account_id'      => $from->id,
            'beneficiary_account_id' => $to->id,
            'amount'                 => 100,
            'frequency'              => $frequency,
            'start_date'             => today()->toDateString(),
        ], $overrides));
    }

    public function test_a_due_order_executes_and_advances_its_next_execution_date(): void
    {
        $from = $this->makeAccount(1000);
        $to   = $this->makeAccount(0);
        $order = $this->makeOrder($from, $to);

        $results = $this->service->executeDue();

        $this->assertEquals(1, $results['success']);
        $this->assertEquals(900, (float) $from->fresh()->balance);
        $this->assertEquals(100, (float) $to->fresh()->balance);
        $this->assertTrue($order->fresh()->next_execution_date->gt(today()));
        $this->assertEquals(1, $order->fresh()->executions_count);
    }

    public function test_an_order_is_not_executed_again_before_its_next_execution_date(): void
    {
        $from = $this->makeAccount(1000);
        $to   = $this->makeAccount(0);
        $this->makeOrder($from, $to);

        $this->service->executeDue();
        $results = $this->service->executeDue(); // same day, already advanced past today

        $this->assertEquals(0, $results['success']);
        $this->assertEquals(0, $results['failed']);
        $this->assertEquals(900, (float) $from->fresh()->balance);
    }

    public function test_an_order_past_its_end_date_is_marked_expired_and_skipped(): void
    {
        $from = $this->makeAccount(1000);
        $to   = $this->makeAccount(0);
        $order = $this->makeOrder($from, $to, overrides: ['end_date' => today()->subDay()->toDateString()]);

        $results = $this->service->executeDue();

        $this->assertEquals(1, $results['skipped']);
        $this->assertEquals('expired', $order->fresh()->status);
        $this->assertEquals(1000, (float) $from->fresh()->balance);
    }

    public function test_insufficient_balance_marks_the_order_failed_without_throwing(): void
    {
        $from = $this->makeAccount(10);
        $to   = $this->makeAccount(0);
        $order = $this->makeOrder($from, $to, overrides: ['amount' => 500]);

        $results = $this->service->executeDue();

        $this->assertEquals(1, $results['failed']);
        $this->assertEquals('failed', $order->fresh()->last_execution_status);
        $this->assertEquals('active', $order->fresh()->status, 'A failed run should retry, not silently deactivate.');
        $this->assertEquals(10, (float) $from->fresh()->balance);
    }

    public function test_pause_resume_and_cancel_enforce_valid_state_transitions(): void
    {
        $from = $this->makeAccount(1000);
        $to   = $this->makeAccount(0);
        $order = $this->makeOrder($from, $to);

        $this->service->pause($order);
        $this->assertEquals('paused', $order->fresh()->status);

        $this->expectException(ValidationException::class);
        $this->service->pause($order->fresh());
    }

    public function test_a_cancelled_order_cannot_be_cancelled_again(): void
    {
        $from = $this->makeAccount(1000);
        $to   = $this->makeAccount(0);
        $order = $this->makeOrder($from, $to);

        $this->service->cancel($order);
        $this->assertEquals('cancelled', $order->fresh()->status);

        $this->expectException(ValidationException::class);
        $this->service->cancel($order->fresh());
    }
}
