<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CustomerService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerTransferTest extends TestCase
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
            'name'  => "Customer {$seq}",
            'email' => "cust{$seq}@example.com",
            'phone' => "2520610{$seq}0001",
        ]);
        $customer->forceFill(['status' => 'active', 'must_change_password' => false, 'two_factor_enabled' => false])->save();

        $account = Account::create([
            'account_number'  => "ACC-{$seq}",
            'customer_id'     => $customer->id,
            'branch_id'       => $this->branch->id,
            'account_type'    => 'savings',
            'status'          => 'active',
            'balance'         => $balance,
            'currency'        => 'USD',
            'opening_date'    => today(),
            'opened_by'       => User::factory()->create()->id,
            'minimum_balance' => 0,
        ]);

        return [$customer, $account];
    }

    public function test_a_customer_can_transfer_between_two_active_accounts(): void
    {
        [$customer, $from] = $this->makeCustomerWithAccount(1000);
        [, $to] = $this->makeCustomerWithAccount(0);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.transfer.store'), [
                'from_account_id' => $from->id,
                'to_account_id'   => $to->id,
                'amount'          => 250,
                'description'     => 'Rent',
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(750, (float) $from->fresh()->balance);
        $this->assertEquals(250, (float) $to->fresh()->balance);

        $txn = Transaction::where('account_id', $from->id)->where('type', 'transfer')->first();
        $this->assertEquals($customer->id, $txn->initiated_by_customer_id);
    }

    public function test_a_customer_cannot_transfer_from_an_account_they_do_not_own(): void
    {
        [$customer] = $this->makeCustomerWithAccount(1000);
        [, $someoneElsesAccount] = $this->makeCustomerWithAccount(500);
        [, $to] = $this->makeCustomerWithAccount(0);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.transfer.store'), [
                'from_account_id' => $someoneElsesAccount->id,
                'to_account_id'   => $to->id,
                'amount'          => 100,
            ])
            ->assertForbidden();

        $this->assertEquals(500, (float) $someoneElsesAccount->fresh()->balance);
    }
}
