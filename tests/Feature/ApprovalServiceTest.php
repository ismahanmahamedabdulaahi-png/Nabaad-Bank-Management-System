<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\TransactionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $initiator;
    private User $approver;
    private ApprovalService $approvals;
    private TransactionService $transactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        DB::table('settings')->insert([
            'key' => 'txn_no_approval_max', 'value' => '100', 'group' => 'system',
            'label' => 'x', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->branch   = Branch::create(['name' => 'Test Branch', 'code' => 'TST', 'status' => 'active']);
        $this->initiator = User::factory()->create();
        $this->approver  = User::factory()->create();
        $this->approver->assignRole(Role::findByName('Branch Manager', 'web'));

        $this->approvals    = app(ApprovalService::class);
        $this->transactions = app(TransactionService::class);
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
            'opened_by'       => $this->initiator->id,
            'minimum_balance' => 0,
        ]);
    }

    private function makePendingDeposit(float $amount, float $startingBalance = 100): array
    {
        $account = $this->makeAccount($startingBalance);
        Auth::login($this->initiator);
        $txn = $this->transactions->deposit($account, ['amount' => $amount]);

        return [$account, $txn];
    }

    public function test_approving_executes_the_transaction_and_updates_balance_once(): void
    {
        [$account, $txn] = $this->makePendingDeposit(500);
        $this->assertEquals('pending', $txn->status);

        Auth::login($this->approver);
        $this->approvals->approve($txn->fresh(), 'Looks fine');

        $fresh = $txn->fresh();
        $this->assertEquals('completed', $fresh->status);
        $this->assertEquals(600, (float) $account->fresh()->balance);

        // Approving again must not double-execute — it's no longer pending.
        $this->expectException(ValidationException::class);
        $this->approvals->approve($fresh, 'Trying to approve an already-completed transaction');
    }

    public function test_rejecting_leaves_the_balance_untouched(): void
    {
        [$account, $txn] = $this->makePendingDeposit(500, 100);

        Auth::login($this->approver);
        $this->approvals->reject($txn->fresh(), 'Suspicious amount');

        $this->assertEquals('rejected', $txn->fresh()->status);
        $this->assertEquals(100, (float) $account->fresh()->balance);
    }

    public function test_the_initiator_cannot_approve_their_own_transaction(): void
    {
        [, $txn] = $this->makePendingDeposit(500);

        Auth::login($this->initiator);
        $this->expectException(ValidationException::class);
        $this->approvals->approve($txn->fresh(), 'Self-approval attempt');
    }

    public function test_an_approver_cannot_approve_out_of_sequence(): void
    {
        // Force a two-level approval so one approval isn't enough to execute.
        DB::table('settings')->insert([
            'key' => 'txn_approval_level1_max', 'value' => '200', 'group' => 'system',
            'label' => 'x', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now(),
        ]);

        [, $txn] = $this->makePendingDeposit(10000);
        $this->assertGreaterThanOrEqual(2, $txn->approval_level_required);

        Auth::login($this->approver);
        $this->approvals->approve($txn->fresh(), 'Level 1 approval');

        $this->expectException(ValidationException::class);
        $this->approvals->approve($txn->fresh(), 'Level 1 cannot approve Level 2');
    }

    public function test_transaction_only_executes_once_the_required_approval_level_is_reached(): void
    {
        DB::table('settings')->insert([
            'key' => 'txn_approval_level1_max', 'value' => '200', 'group' => 'system',
            'label' => 'x', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now(),
        ]);

        [$account, $txn] = $this->makePendingDeposit(10000, 100);
        $this->assertEquals(2, $txn->approval_level_required);

        Auth::login($this->approver);
        $this->approvals->approve($txn->fresh(), 'Level 1 sign-off');

        // Still pending — only one of two required approval levels reached.
        $this->assertEquals('pending', $txn->fresh()->status);
        $this->assertEquals(100, (float) $account->fresh()->balance);

        $complianceOfficer = User::factory()->create();
        $complianceOfficer->assignRole(Role::findByName('Compliance Officer', 'web'));
        Auth::login($complianceOfficer);
        $this->approvals->approve($txn->fresh(), 'Level 2 sign-off');

        $this->assertEquals('completed', $txn->fresh()->status);
        $this->assertEquals(10100, (float) $account->fresh()->balance);
    }

    public function test_level_three_transaction_requires_all_three_approval_roles_in_order(): void
    {
        DB::table('settings')->insert([
            ['key' => 'txn_approval_level1_max', 'value' => '200', 'group' => 'system',
             'label' => 'x', 'type' => 'decimal', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'txn_approval_level2_max', 'value' => '500', 'group' => 'system',
             'label' => 'x', 'type' => 'decimal', 'created_at' => now(), 'updated_at' => now()],
        ]);

        [$account, $txn] = $this->makePendingDeposit(1000, 100);
        $this->assertEquals(3, $txn->approval_level_required);

        Auth::login($this->approver);
        $this->approvals->approve($txn->fresh(), 'Level 1 approval');
        $this->assertEquals(1, $txn->fresh()->approval_level_reached);
        $this->assertEquals('pending', $txn->fresh()->status);

        $complianceOfficer = User::factory()->create();
        $complianceOfficer->assignRole(Role::findByName('Compliance Officer', 'web'));
        Auth::login($complianceOfficer);
        $this->approvals->approve($txn->fresh(), 'Level 2 approval');
        $this->assertEquals(2, $txn->fresh()->approval_level_reached);
        $this->assertEquals('pending', $txn->fresh()->status);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::findByName('Super Admin', 'web'));
        Auth::login($superAdmin);
        $this->approvals->approve($txn->fresh(), 'Level 3 approval');

        $this->assertEquals('completed', $txn->fresh()->status);
        $this->assertEquals(1100, (float) $account->fresh()->balance);
    }

    public function test_super_admin_can_approve_a_level_three_transaction_from_the_pending_queue(): void
    {
        DB::table('settings')->insert([
            ['key' => 'txn_approval_level1_max', 'value' => '200', 'group' => 'system',
             'label' => 'x', 'type' => 'decimal', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'txn_approval_level2_max', 'value' => '500', 'group' => 'system',
             'label' => 'x', 'type' => 'decimal', 'created_at' => now(), 'updated_at' => now()],
        ]);

        [$account, $txn] = $this->makePendingDeposit(1000, 100);
        $this->assertEquals(3, $txn->approval_level_required);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::findByName('Super Admin', 'web'));
        Auth::login($superAdmin);

        $this->assertTrue($this->approvals->pendingForUser($superAdmin)->contains('id', $txn->id));
        $this->approvals->approve($txn->fresh(), 'Super Admin authorization');

        $this->assertEquals('completed', $txn->fresh()->status);
        $this->assertDatabaseHas('transaction_approvals', [
            'transaction_id' => $txn->id,
            'approver_id' => $superAdmin->id,
            'level' => 3,
            'action' => 'approved',
        ]);
        $this->assertEquals(1100, (float) $account->fresh()->balance);
    }

    public function test_super_admin_still_cannot_approve_their_own_transaction(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::findByName('Super Admin', 'web'));
        $this->initiator = $superAdmin;

        [, $txn] = $this->makePendingDeposit(500);

        Auth::login($superAdmin);
        $this->expectException(ValidationException::class);
        $this->approvals->approve($txn->fresh(), 'Self-approval attempt');
    }
}
