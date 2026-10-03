<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ServiceRequestCode;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\ServiceCodeService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
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

    private function makeTeller(): User
    {
        $teller = User::factory()->create();
        $teller->assignRole(Role::findByName('Teller', 'web'));
        return $teller;
    }

    public function test_requesting_a_withdrawal_code_creates_a_pending_transaction_that_does_not_touch_the_balance(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);

        $code = $this->service->requestWithdrawal($customer, $account, 200, null);

        $this->assertEquals('pending', $code->status);
        $this->assertEquals(6, strlen($code->code));
        $this->assertEquals('pending', $code->transaction->status);
        $this->assertEquals(1000, (float) $account->fresh()->balance, 'Balance must be unaffected until redemption.');
    }

    public function test_requesting_a_withdrawal_larger_than_the_balance_is_rejected(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(50);

        $this->expectException(ValidationException::class);
        $this->service->requestWithdrawal($customer, $account, 500, null);
    }

    public function test_a_teller_redeeming_a_withdrawal_code_pays_out_and_debits_the_account(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);
        $code = $this->service->requestWithdrawal($customer, $account, 200, null);
        $teller = $this->makeTeller();
        Auth::login($teller);

        $found = $this->service->findActiveCode($code->code);
        $transaction = $this->service->redeemWithdrawal($found, $teller);

        $this->assertEquals(800, (float) $account->fresh()->balance);
        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals('redeemed', $code->fresh()->status);
        $this->assertEquals($teller->id, $code->fresh()->redeemed_by);
    }

    public function test_a_withdrawal_code_cannot_be_redeemed_twice(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);
        $code = $this->service->requestWithdrawal($customer, $account, 200, null);
        $teller = $this->makeTeller();
        Auth::login($teller);

        $found = $this->service->findActiveCode($code->code);
        $this->service->redeemWithdrawal($found, $teller);

        $this->expectException(ValidationException::class);
        $this->service->findActiveCode($code->code);
    }

    public function test_an_expired_withdrawal_code_cannot_be_redeemed(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);
        $code = $this->service->requestWithdrawal($customer, $account, 200, null);
        $code->update(['expires_at' => now()->subMinute()]);

        $this->expectException(ValidationException::class);
        $this->service->findActiveCode($code->code);

        $this->assertEquals('expired', $code->fresh()->status);
    }

    public function test_deposit_pre_register_creates_no_transaction_until_redeemed(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(0);

        $code = $this->service->requestDeposit($customer, $account, 300, null);
        $this->assertNull($code->transaction_id);

        $teller = $this->makeTeller();
        Auth::login($teller);
        $found = $this->service->findActiveCode($code->code);
        $transaction = $this->service->redeemDeposit($found, $teller, 300, null);

        $this->assertEquals(300, (float) $account->fresh()->balance);
        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals($customer->id, $transaction->initiated_by_customer_id);
        $this->assertEquals('redeemed', $code->fresh()->status);
        $this->assertEquals($transaction->id, $code->fresh()->transaction_id);
    }

    public function test_expiring_a_pending_withdrawal_code_rejects_its_pending_transaction(): void
    {
        [$customer, $account] = $this->makeCustomerWithAccount(1000);
        $code = $this->service->requestWithdrawal($customer, $account, 200, null);
        $code->update(['expires_at' => now()->subMinute()]);

        $this->artisan('service-codes:expire')->assertExitCode(0);

        $this->assertEquals('expired', $code->fresh()->status);
        $this->assertEquals('rejected', $code->fresh()->transaction->status);
        $this->assertEquals(1000, (float) $account->fresh()->balance);
    }
}
