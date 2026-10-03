<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\EodRun;
use App\Models\User;
use App\Services\EodService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EodServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;
    private EodService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TST', 'status' => 'active']);
        $this->user   = User::factory()->create();
        $this->actingAs($this->user);

        $this->service = app(EodService::class);
    }

    private function makeAccount(array $overrides = []): Account
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

        return Account::create(array_merge([
            'account_number'  => "ACC-{$seq}",
            'customer_id'     => $customer->id,
            'branch_id'       => $this->branch->id,
            'account_type'    => 'savings',
            'status'          => 'active',
            'balance'         => 1000,
            'currency'        => 'USD',
            'opening_date'    => today()->subYear(),
            'opened_by'       => $this->user->id,
            'minimum_balance' => 0,
        ], $overrides));
    }

    public function test_running_eod_twice_for_the_same_date_does_not_re_run_it(): void
    {
        $first  = $this->service->run(today());
        $second = $this->service->run(today());

        $this->assertEquals('completed', $first->status);
        $this->assertEquals($first->id, $second->id, 'A second run for an already-completed date must return the existing run, not create another.');
        $this->assertEquals(1, EodRun::whereDate('run_date', today())->count());
    }

    public function test_accounts_inactive_past_the_dormancy_threshold_are_marked_dormant(): void
    {
        DB::table('settings')->insert([
            'key' => 'dormancy_months', 'value' => '6', 'group' => 'system',
            'label' => 'x', 'type' => 'integer', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $dormantCandidate = $this->makeAccount(['last_activity_date' => today()->subMonths(7)]);
        $activeAccount    = $this->makeAccount(['last_activity_date' => today()->subDays(2)]);

        $run = $this->service->run(today());

        $this->assertEquals('dormant', $dormantCandidate->fresh()->status);
        $this->assertEquals('active', $activeAccount->fresh()->status);
        $this->assertEquals(1, $run->dormant_accounts);
    }

    public function test_fixed_deposits_past_maturity_are_marked_matured(): void
    {
        $fd = $this->makeAccount([
            'account_type'     => 'fixed_deposit',
            'fd_maturity_date' => today()->subDay(),
        ]);
        $notYetMatured = $this->makeAccount([
            'account_type'     => 'fixed_deposit',
            'fd_maturity_date' => today()->addMonth(),
        ]);

        $run = $this->service->run(today());

        $this->assertEquals('matured', $fd->fresh()->status);
        $this->assertEquals('active', $notYetMatured->fresh()->status);
        $this->assertEquals(1, $run->matured_fds);
    }

    public function test_ran_today_and_latest_run_reflect_a_completed_run(): void
    {
        $this->assertFalse($this->service->ranToday());

        $this->service->run(today());

        $this->assertTrue($this->service->ranToday());
        $this->assertEquals(today()->toDateString(), $this->service->latestRun()->run_date->toDateString());
    }
}
