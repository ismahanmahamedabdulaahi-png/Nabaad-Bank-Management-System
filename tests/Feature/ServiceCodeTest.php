<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ServiceRequestCode;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\ServiceCodeService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceCodeTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private ServiceCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TST', 'status' => 'active']);
        $this->service = app(ServiceCodeService::class);
    }

    private function makeCustomerWithAccount(float $balance): array
    {
        Notification::fake();
        static $seq = 0;
        $seq++;

        $customer = app(CustomerService::class)->create([
            'name' => "Customer {$seq}", 'email' => "cust{$seq}@example.com", 'phone' => "2520610{$seq}0001",
        ]);
        $customer->forceFill(['status' => 'active'])->save();

        $account = Account::create([
            'account_number' => "ACC-{$seq}", 'customer_id' => $customer->id, 'branch_id' => $this->branch->id,
            'account_type' => 'savings', 'status' => 'active', 'balance' => $balance, 'currency' => 'USD',
            'opening_date' => today(), 'opened_by' => User::factory()->create()->id, 'minimum_balance' => 0,
        ]);

        return [$customer, $account];
    }

    public function test_a_customer_can_withdraw_directly_without_a_code(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);

        $transaction = $this->service->withdrawDirect($customer, $account, 250, null);

        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals(750, (float) $account->fresh()->balance);
        $this->assertEquals($customer->id, $transaction->initiated_by_customer_id);
        $this->assertEquals(0, ServiceRequestCode::count(), 'Direct withdrawal must not issue a code.');
    }

    public function test_a_customer_can_deposit_directly_without_a_code(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(100);

        $transaction = $this->service->depositDirect($customer, $account, 50, null);

        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals('deposit', $transaction->type);
        $this->assertEquals(150, (float) $account->fresh()->balance);
        $this->assertEquals(0, ServiceRequestCode::count(), 'Direct deposit must not issue a code.');
    }

    public function test_a_direct_withdrawal_larger_than_the_balance_is_rejected_and_leaves_nothing_behind(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(100);

        try {
            $this->service->withdrawDirect($customer, $account, 500, null);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException) {
        }

        $this->assertEquals(100, (float) $account->fresh()->balance);
        $this->assertEquals(0, $account->transactions()->count());
    }

    public function test_a_customer_cannot_withdraw_from_someone_elses_account(): void
    {
        [$customer]         = $this->makeCustomerWithAccount(1000);
        [, $otherAccount]   = $this->makeCustomerWithAccount(1000);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->withdrawDirect($customer, $otherAccount, 100, null);
    }

    public function test_the_admin_redeem_page_is_gone(): void
    {
        $this->assertFalse(\Route::has('admin.service-codes.create'));
        $this->assertFalse(\Route::has('admin.service-codes.redeem-withdrawal'));
    }

    // Codes issued before the switch to direct withdrawals are still swept
    // up by the scheduled expiry command.
    public function test_expiring_a_legacy_withdrawal_code_rejects_its_pending_transaction(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);

        $txn = Transaction::create([
            'reference' => 'TXN-LEGACY-1', 'account_id' => $account->id, 'type' => 'withdrawal',
            'amount' => 200, 'balance_before' => 1000, 'balance_after' => 800, 'status' => 'pending',
            'currency' => 'USD', 'requires_approval' => false, 'initiated_by_customer_id' => $customer->id,
        ]);
        $code = ServiceRequestCode::create([
            'customer_id' => $customer->id, 'account_id' => $account->id, 'type' => 'withdrawal',
            'amount' => 200, 'code' => '123456', 'status' => 'pending', 'transaction_id' => $txn->id,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('service-codes:expire')->assertExitCode(0);

        $this->assertEquals('expired', $code->fresh()->status);
        $this->assertEquals('rejected', $txn->fresh()->status);
        $this->assertEquals(1000, (float) $account->fresh()->balance);
    }
}
