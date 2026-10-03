<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\ServiceCodeService;
use App\Services\TransactionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Regression coverage for a real bug: deactivating a customer never touched
// their accounts' own `status` column, so every money-movement path — which
// only ever checked Account::isOperational() — kept working for a deactivated
// customer regardless. Fixed by having isOperational() also require the
// owning customer to be active (see app/Models/Account.php).
class DeactivatedCustomerTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TST', 'status' => 'active']);
    }

    private function makeCustomerWithAccount(float $balance): array
    {
        Notification::fake();
        static $seq = 0;
        $seq++;

        $customer = app(CustomerService::class)->create([
            'name' => "Customer {$seq}", 'email' => "cust{$seq}@example.com", 'phone' => "2520610{$seq}0001",
        ]);
        $customer->forceFill(['status' => 'active', 'must_change_password' => false, 'two_factor_enabled' => false])->save();

        $account = Account::create([
            'account_number' => "ACC-{$seq}", 'customer_id' => $customer->id, 'branch_id' => $this->branch->id,
            'account_type' => 'savings', 'status' => 'active', 'balance' => $balance, 'currency' => 'USD',
            'opening_date' => today(), 'opened_by' => User::factory()->create()->id, 'minimum_balance' => 0,
        ]);

        return [$customer, $account];
    }

    public function test_a_deactivated_customers_account_is_not_operational_even_though_the_account_row_itself_is_active(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);
        $this->assertTrue($account->isOperational());

        $customer->update(['status' => 'inactive']);

        $this->assertEquals('active', $account->fresh()->status, 'The account row itself is untouched by deactivation.');
        $this->assertFalse($account->fresh()->isOperational(), 'But it must no longer be usable once the owner is deactivated.');
    }

    public function test_a_teller_cannot_deposit_into_a_deactivated_customers_account(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);
        $customer->update(['status' => 'inactive']);

        $this->expectException(ValidationException::class);
        app(TransactionService::class)->deposit($account->fresh(), ['amount' => 100]);
    }

    public function test_a_teller_cannot_withdraw_from_a_blacklisted_customers_account(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);
        $customer->update(['status' => 'blacklisted']);

        $this->expectException(ValidationException::class);
        app(TransactionService::class)->withdraw($account->fresh(), ['amount' => 50]);
    }

    public function test_a_transfer_is_blocked_if_either_party_is_a_deactivated_customer(): void
    {
        [, $from] = $this->makeCustomerWithAccount(1000);
        [$toCustomer, $to] = $this->makeCustomerWithAccount(0);
        $toCustomer->update(['status' => 'inactive']);

        $this->expectException(ValidationException::class);
        app(TransactionService::class)->transfer($from, $to->fresh(), ['amount' => 100]);
    }

    public function test_a_deactivated_customer_cannot_request_a_cardless_withdrawal_code(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);
        $customer->update(['status' => 'inactive']);

        $this->expectException(ValidationException::class);
        app(ServiceCodeService::class)->requestWithdrawal($customer, $account->fresh(), 100, null);
    }

    public function test_reactivating_the_customer_restores_normal_use_of_their_account(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);
        $customer->update(['status' => 'inactive']);
        $customer->update(['status' => 'active']);

        $txn = app(TransactionService::class)->deposit($account->fresh(), ['amount' => 100]);
        $this->assertEquals('completed', $txn->status);
        $this->assertEquals(1100, (float) $account->fresh()->balance);
    }

    public function test_an_already_logged_in_customer_is_logged_out_the_moment_they_are_deactivated(): void
    {
        [$customer] = $this->makeCustomerWithAccount(1000);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk();

        $customer->update(['status' => 'inactive']);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertRedirect(route('customer.login'));
    }
}
