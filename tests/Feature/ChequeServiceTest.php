<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\User;
use App\Services\ChequeService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChequeServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $user;
    private ChequeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TST', 'status' => 'active']);
        $this->user   = User::factory()->create();
        $this->actingAs($this->user);

        $this->service = app(ChequeService::class);
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

    private function issueOneCheque(Account $account): Cheque
    {
        $book = $this->service->issueBook($account->customer, $account, ['total_leaves' => 5]);

        return Cheque::where('cheque_book_id', $book->id)->first();
    }

    public function test_issuing_a_book_creates_the_requested_number_of_leaves(): void
    {
        $account = $this->makeAccount(1000);
        $book = $this->service->issueBook($account->customer, $account, ['total_leaves' => 10]);

        $this->assertEquals(10, $book->total_leaves);
        $this->assertEquals(10, Cheque::where('cheque_book_id', $book->id)->count());
        $this->assertTrue(Cheque::where('cheque_book_id', $book->id)->get()->every(fn ($c) => $c->status === 'issued'));
    }

    public function test_encashing_a_cheque_debits_the_drawer_account_and_marks_it_used(): void
    {
        $account = $this->makeAccount(1000);
        $cheque  = $this->issueOneCheque($account);

        $result = $this->service->encashCheque($cheque, ['amount' => 300, 'payee_name' => 'John Doe']);

        $this->assertEquals(700, (float) $account->fresh()->balance);
        $this->assertEquals('used', $result->status);
        $this->assertNotNull($result->transaction_id);
    }

    public function test_encashing_rejects_insufficient_drawer_balance(): void
    {
        $account = $this->makeAccount(100);
        $cheque  = $this->issueOneCheque($account);

        $this->expectException(ValidationException::class);
        $this->service->encashCheque($cheque, ['amount' => 500, 'payee_name' => 'John Doe']);
    }

    public function test_a_used_cheque_cannot_be_presented_twice(): void
    {
        $account = $this->makeAccount(1000);
        $cheque  = $this->issueOneCheque($account);
        $this->service->encashCheque($cheque, ['amount' => 100, 'payee_name' => 'John Doe']);

        $this->expectException(ValidationException::class);
        $this->service->encashCheque($cheque->fresh(), ['amount' => 100, 'payee_name' => 'John Doe']);
    }

    public function test_deposited_cheque_debits_drawer_immediately_and_credits_beneficiary_only_on_clearing(): void
    {
        $drawer      = $this->makeAccount(1000);
        $beneficiary = $this->makeAccount(200);
        $cheque      = $this->issueOneCheque($drawer);

        $result = $this->service->depositCheque($cheque, [
            'amount' => 400, 'payee_name' => 'Jane Doe', 'beneficiary_account_id' => $beneficiary->id,
        ]);

        $this->assertEquals(600, (float) $drawer->fresh()->balance);
        $this->assertEquals(200, (float) $beneficiary->fresh()->balance, 'Beneficiary must not be credited until clearing.');
        $this->assertEquals('pending_clearance', $result->status);

        // Fast-forward past the clearing date and run the batch job.
        $result->update(['clearing_date' => today()->subDay()]);
        $cleared = $this->service->processClearing();

        $this->assertEquals(1, $cleared);
        $this->assertEquals(600, (float) $beneficiary->fresh()->balance);
        $this->assertEquals('cleared', $result->fresh()->status);
    }

    public function test_bouncing_a_pending_cheque_refunds_the_drawer_and_frees_the_book_leaf(): void
    {
        $drawer      = $this->makeAccount(1000);
        $beneficiary = $this->makeAccount(200);
        $cheque      = $this->issueOneCheque($drawer);

        $this->service->depositCheque($cheque, [
            'amount' => 400, 'payee_name' => 'Jane Doe', 'beneficiary_account_id' => $beneficiary->id,
        ]);
        $this->assertEquals(600, (float) $drawer->fresh()->balance);

        $book = $cheque->fresh()->chequeBook;
        $usedBefore = $book->fresh()->used_leaves;

        $bounced = $this->service->bounceCheque($cheque->fresh(), 'Insufficient funds confirmed by drawer bank');

        $this->assertEquals(1000, (float) $drawer->fresh()->balance, 'Drawer must be refunded on bounce.');
        $this->assertEquals('bounced', $bounced->status);
        $this->assertEquals($usedBefore - 1, $book->fresh()->used_leaves);
    }

    public function test_only_issued_cheques_can_be_cancelled(): void
    {
        $account = $this->makeAccount(1000);
        $cheque  = $this->issueOneCheque($account);
        $this->service->encashCheque($cheque, ['amount' => 50, 'payee_name' => 'X']);

        $this->expectException(ValidationException::class);
        $this->service->cancelCheque($cheque->fresh(), 'Too late, already used');
    }
}
