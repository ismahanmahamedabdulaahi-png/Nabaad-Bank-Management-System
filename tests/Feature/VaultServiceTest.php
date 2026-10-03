<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BusinessDay;
use App\Models\TellerTill;
use App\Models\User;
use App\Models\Vault;
use App\Services\VaultService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VaultServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Vault $vault;
    private VaultService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $branch = Branch::create(['name' => 'Test Branch', 'code' => 'TST', 'status' => 'active']);
        $this->vault = Vault::create(['branch_id' => $branch->id, 'balance' => 1000, 'currency' => 'USD', 'status' => 'closed']);

        BusinessDay::create(['business_date' => today(), 'status' => 'open', 'opened_by' => $this->user->id, 'opened_at' => now()]);

        $this->service = app(VaultService::class);
    }

    private function makeOpenTill(float $balance = 0): TellerTill
    {
        return TellerTill::create([
            'till_name' => 'Till 1',
            'teller_id' => $this->user->id,
            'assigned_by' => $this->user->id,
            'status' => 'open',
            'opening_balance' => $balance,
            'current_balance' => $balance,
            'business_date' => today(),
            'opened_at' => now(),
            'opened_by' => $this->user->id,
        ]);
    }

    public function test_vault_cannot_transact_before_the_business_day_is_open(): void
    {
        BusinessDay::query()->delete();

        $this->expectException(ValidationException::class);
        $this->service->cashIn(100, null);
    }

    public function test_cash_in_and_cash_out_move_the_vault_balance(): void
    {
        $this->service->open(null);

        $this->service->cashIn(500, 'Cash delivery');
        $this->assertEquals(1500, (float) $this->vault->fresh()->balance);

        $this->service->cashOut(300, 'Cash pickup');
        $this->assertEquals(1200, (float) $this->vault->fresh()->balance);
    }

    public function test_cash_out_rejects_amounts_exceeding_the_vault_balance(): void
    {
        $this->service->open(null);

        $this->expectException(ValidationException::class);
        $this->service->cashOut(999999, null);
    }

    public function test_vault_cannot_transact_while_closed(): void
    {
        // Vault starts closed and open() was never called.
        $this->expectException(ValidationException::class);
        $this->service->cashIn(100, null);
    }

    public function test_transfer_to_teller_moves_cash_from_vault_to_till(): void
    {
        $this->service->open(null);
        $till = $this->makeOpenTill(0);

        $this->service->transferToTeller($till, 200, 'Opening float');

        $this->assertEquals(800, (float) $this->vault->fresh()->balance);
        $this->assertEquals(200, (float) $till->fresh()->current_balance);
    }

    public function test_receive_from_teller_moves_cash_back_to_vault(): void
    {
        $this->service->open(null);
        $till = $this->makeOpenTill(500);

        $this->service->receiveFromTeller($till, 200, 'End of day return');

        $this->assertEquals(1200, (float) $this->vault->fresh()->balance);
        $this->assertEquals(300, (float) $till->fresh()->current_balance);
    }

    public function test_vault_cannot_close_while_a_till_is_still_open(): void
    {
        $this->service->open(null);
        $this->makeOpenTill();

        $this->expectException(ValidationException::class);
        $this->service->close(null);
    }
}
