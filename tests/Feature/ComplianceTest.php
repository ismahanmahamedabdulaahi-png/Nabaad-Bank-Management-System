<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\ComplianceCase;
use App\Models\Customer;
use App\Models\User;
use App\Services\ComplianceService;
use App\Services\CustomerService;
use App\Services\TransactionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private ComplianceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TST', 'status' => 'active']);
        $this->service = app(ComplianceService::class);
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

    public function test_manually_flagging_a_customer_opens_a_case_and_notifies_compliance_staff_internally_only(): void
    {
        [$customer] = $this->makeCustomerWithAccount(1000);
        $officer = User::factory()->create();
        $officer->assignRole(Role::findByName('Compliance Officer', 'web'));
        $staff = User::factory()->create();

        $case = $this->service->flagManually($customer, null, 'Unusual pattern of activity.', $staff->id, 'high');

        $this->assertEquals('manual_flag', $case->type);
        $this->assertEquals('open', $case->status);
        $this->assertEquals($staff->id, $case->flagged_by);

        // In-app only, and only to staff with compliance.manage — never the customer.
        Notification::assertSentTo($officer, \App\Notifications\ComplianceCaseOpened::class);
        Notification::assertNotSentTo($customer, \App\Notifications\ComplianceCaseOpened::class);
    }

    public function test_flag_action_returns_to_the_previous_page_without_compliance_view_permission(): void
    {
        [$customer] = $this->makeCustomerWithAccount(1000);
        $role = Role::create(['name' => 'Compliance Flagger', 'guard_name' => 'web']);
        $role->givePermissionTo('compliance.manage');
        $staff = User::factory()->create();
        $staff->assignRole($role);

        $this->assertFalse($staff->can('compliance.view'));

        $response = $this->withoutMiddleware()
            ->actingAs($staff)
            ->from(route('admin.customers.show', $customer->id))
            ->post(route('admin.compliance.flag'), [
                'customer_id' => $customer->id,
                'notes' => 'Review unusual account activity.',
                'severity' => 'medium',
            ]);

        $response->assertRedirect(route('admin.customers.show', $customer->id));
        $this->assertDatabaseHas('compliance_cases', [
            'customer_id' => $customer->id,
            'type' => 'manual_flag',
            'flagged_by' => $staff->id,
        ]);
    }

    public function test_large_transaction_report_returns_only_transactions_at_or_above_the_threshold(): void
    {
        DB::table('settings')->insert([
            ['key' => 'aml_large_txn_threshold', 'value' => '5000', 'group' => 'system',
             'label' => 'x', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now()],
            // Keep the approval threshold above the test amounts so both
            // deposits complete instantly rather than sitting pending.
            ['key' => 'txn_no_approval_max', 'value' => '10000', 'group' => 'system',
             'label' => 'x', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now()],
        ]);

        [$customer, $account] = $this->makeCustomerWithAccount(0);
        $staff = User::factory()->create();
        Auth::login($staff);

        app(TransactionService::class)->deposit($account, ['amount' => 6000]);
        app(TransactionService::class)->deposit($account->fresh(), ['amount' => 500]);

        $report = $this->service->largeTransactions(today()->subDay()->toDateString(), today()->toDateString());

        $this->assertCount(1, $report);
        $this->assertEquals(6000, (float) $report->first()->amount);
    }

    public function test_structuring_detection_flags_repeated_sub_threshold_transactions_without_notifying_the_customer(): void
    {
        DB::table('settings')->insert([
            'key' => 'aml_large_txn_threshold', 'value' => '5000', 'group' => 'system',
            'label' => 'x', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now(),
        ]);

        [$customer, $account] = $this->makeCustomerWithAccount(0);
        $staff = User::factory()->create();
        Auth::login($staff);

        // Three deposits each just under the threshold, summing well above it.
        $txnService = app(TransactionService::class);
        $txnService->deposit($account, ['amount' => 4600]);
        $txnService->deposit($account->fresh(), ['amount' => 4700]);
        $txnService->deposit($account->fresh(), ['amount' => 4800]);

        $opened = $this->service->detectStructuring();

        $this->assertEquals(1, $opened);
        $case = ComplianceCase::where('customer_id', $customer->id)->where('type', 'structuring')->first();
        $this->assertNotNull($case);
        $this->assertNull($case->flagged_by, 'System-generated cases have no human flagger.');

        Notification::assertNotSentTo($customer, \App\Notifications\ComplianceCaseOpened::class);
    }

    public function test_structuring_detection_does_not_open_a_duplicate_case_for_an_already_open_one(): void
    {
        DB::table('settings')->insert([
            'key' => 'aml_large_txn_threshold', 'value' => '5000', 'group' => 'system',
            'label' => 'x', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now(),
        ]);

        [$customer, $account] = $this->makeCustomerWithAccount(0);
        $staff = User::factory()->create();
        Auth::login($staff);

        $txnService = app(TransactionService::class);
        $txnService->deposit($account, ['amount' => 4600]);
        $txnService->deposit($account->fresh(), ['amount' => 4700]);
        $txnService->deposit($account->fresh(), ['amount' => 4800]);

        $this->service->detectStructuring();
        $second = $this->service->detectStructuring();

        $this->assertEquals(0, $second);
        $this->assertEquals(1, ComplianceCase::where('customer_id', $customer->id)->count());
    }

    public function test_ordinary_activity_below_the_structuring_pattern_is_not_flagged(): void
    {
        DB::table('settings')->insert([
            'key' => 'aml_large_txn_threshold', 'value' => '5000', 'group' => 'system',
            'label' => 'x', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now(),
        ]);

        [$customer, $account] = $this->makeCustomerWithAccount(0);
        $staff = User::factory()->create();
        Auth::login($staff);

        // Only two sub-threshold transactions — below the 3-transaction floor.
        $txnService = app(TransactionService::class);
        $txnService->deposit($account, ['amount' => 4600]);
        $txnService->deposit($account->fresh(), ['amount' => 4700]);

        $opened = $this->service->detectStructuring();

        $this->assertEquals(0, $opened);
    }
}
