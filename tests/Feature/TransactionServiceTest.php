<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user   = User::factory()->create();
        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TST', 'status' => 'active']);
        $this->actingAs($this->user);

        $this->service = app(TransactionService::class);
    }

    private function makeAccount(float $balance = 1000, float $minimumBalance = 0): Account
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
            'minimum_balance' => $minimumBalance,
        ]);
    }

    public function test_deposit_increases_balance_and_completes_immediately(): void
    {
        $account = $this->makeAccount(100);

        $txn = $this->service->deposit($account, ['amount' => 250]);

        $this->assertEquals(350, (float) $account->fresh()->balance);
        $this->assertEquals('completed', $txn->status);
        $this->assertEquals(100, (float) $txn->balance_before);
        $this->assertEquals(350, (float) $txn->balance_after);
    }

    public function test_withdrawal_decreases_balance_and_rejects_insufficient_funds(): void
    {
        $account = $this->makeAccount(100);

        $txn = $this->service->withdraw($account, ['amount' => 40]);
        $this->assertEquals(60, (float) $account->fresh()->balance);
        $this->assertEquals('completed', $txn->status);

        $this->expectException(ValidationException::class);
        $this->service->withdraw($account->fresh(), ['amount' => 1000]);
    }

    public function test_withdrawal_rejects_breach_of_minimum_balance(): void
    {
        $account = $this->makeAccount(100, minimumBalance: 50);

        $this->expectException(ValidationException::class);
        $this->service->withdraw($account, ['amount' => 70]);
    }

    public function test_transfer_creates_two_rows_debit_and_cr_credit_leg(): void
    {
        $from = $this->makeAccount(500);
        $to   = $this->makeAccount(100);

        [$fromTxn, $toTxn] = $this->service->transfer($from, $to, ['amount' => 200]);

        $this->assertEquals(300, (float) $from->fresh()->balance);
        $this->assertEquals(300, (float) $to->fresh()->balance);

        $this->assertEquals($fromTxn->reference, $fromTxn->reference);
        $this->assertEquals($fromTxn->reference . '-CR', $toTxn->reference);
        $this->assertEquals('transfer', $fromTxn->type);
        $this->assertEquals('transfer', $toTxn->type);
        $this->assertEquals(200, (float) $fromTxn->amount);
        $this->assertEquals(200, (float) $toTxn->amount);

        // Debit leg balance fell, credit leg balance rose — this is the
        // convention the rest of the app (dashboards, reports) relies on to
        // tell which leg is which without a dedicated "direction" column.
        $this->assertTrue($fromTxn->balance_after < $fromTxn->balance_before);
        $this->assertTrue($toTxn->balance_after > $toTxn->balance_before);
    }

    public function test_transfer_above_approval_threshold_creates_pending_rows_without_touching_balance(): void
    {
        DB::table('settings')->insert([
            'key' => 'txn_no_approval_max', 'value' => '500', 'group' => 'system',
            'label' => 'x', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $from = $this->makeAccount(5000);
        $to   = $this->makeAccount(100);

        [$fromTxn, $toTxn] = $this->service->transfer($from, $to, ['amount' => 1000]);

        $this->assertEquals('pending', $fromTxn->status);
        $this->assertEquals('pending', $toTxn->status);
        $this->assertTrue($fromTxn->requires_approval);

        // Nothing actually moved yet — only approval executes it.
        $this->assertEquals(5000, (float) $from->fresh()->balance);
        $this->assertEquals(100, (float) $to->fresh()->balance);
    }

    public function test_deposit_above_approval_threshold_creates_a_pending_transaction(): void
    {
        DB::table('settings')->insert([
            'key' => 'txn_no_approval_max', 'value' => '500', 'group' => 'system',
            'label' => 'x', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $account = $this->makeAccount(100);

        $txn = $this->service->deposit($account, ['amount' => 1000]);

        $this->assertEquals('pending', $txn->status);
        $this->assertEquals(100, (float) $account->fresh()->balance);
    }

    public function test_reversing_a_deposit_restores_the_prior_balance(): void
    {
        $account = $this->makeAccount(100);
        $deposit = $this->service->deposit($account, ['amount' => 300]);
        $this->assertEquals(400, (float) $account->fresh()->balance);

        $reversal = $this->service->reverse($deposit->fresh(), 'Customer disputed the deposit');

        $this->assertEquals(100, (float) $account->fresh()->balance);
        $this->assertEquals('reversal', $reversal->type);
        $this->assertEquals($deposit->id, $reversal->reversal_of);
    }

    public function test_a_transaction_cannot_be_reversed_twice(): void
    {
        $account = $this->makeAccount(100);
        $deposit = $this->service->deposit($account, ['amount' => 300]);
        $this->service->reverse($deposit->fresh(), 'First reversal');

        $this->expectException(ValidationException::class);
        $this->service->reverse($deposit->fresh(), 'Second attempt');
    }

    public function test_a_reversal_itself_cannot_be_reversed(): void
    {
        $account = $this->makeAccount(100);
        $deposit = $this->service->deposit($account, ['amount' => 300]);
        $reversal = $this->service->reverse($deposit->fresh(), 'Reverse it');

        $this->expectException(ValidationException::class);
        $this->service->reverse($reversal->fresh(), 'Reverse the reversal');
    }
}
