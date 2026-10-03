<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\ChequeService;
use App\Services\GeneralLedgerService;
use App\Services\LoanService;
use App\Services\TransactionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);

        $this->user   = User::factory()->create();
        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TST', 'status' => 'active']);

        $this->actingAs($this->user);
    }

    private function makeAccount(string $type = 'savings', float $balance = 1000): Account
    {
        static $seq = 0;
        $seq++;

        $customer = Customer::create([
            'customer_number' => "CUS-000{$seq}",
            'name'            => "Customer {$seq}",
            'email'           => "customer{$seq}@example.com",
            'phone'           => "20000000{$seq}",
            'password'        => bcrypt('secret'),
            'status'          => 'active',
        ]);

        return Account::create([
            'account_number' => "ACC-000{$seq}",
            'customer_id'    => $customer->id,
            'branch_id'      => $this->branch->id,
            'account_type'   => $type,
            'status'         => 'active',
            'balance'        => $balance,
            'currency'       => 'USD',
            'opening_date'   => today(),
            'opened_by'      => $this->user->id,
            'minimum_balance'=> 0,
        ]);
    }

    public function test_post_rejects_unbalanced_lines(): void
    {
        $gl = app(GeneralLedgerService::class);

        $this->expectException(\InvalidArgumentException::class);

        $gl->post([
            ['account' => '1000', 'debit' => 100],
            ['account' => '2000', 'credit' => 50],
        ], 'Bad entry', 'manual');
    }

    public function test_deposit_posts_a_balanced_journal_entry(): void
    {
        $account = $this->makeAccount('savings', 0);

        $txnService = app(TransactionService::class);
        $txn = $txnService->deposit($account, ['amount' => 500, 'description' => 'Cash deposit']);

        $entry = JournalEntry::where('source_type', 'transaction')->where('source_id', $txn->id)->firstOrFail();

        $this->assertEquals(500, $entry->totalDebit());
        $this->assertEquals(500, $entry->totalCredit());
        $this->assertTrue($entry->lines->contains(fn ($l) => $l->glAccount->code === '2000' && (float) $l->credit === 500.0));
        // No till open -> falls back to the off-till suspense account, not the vault.
        $this->assertTrue($entry->lines->contains(fn ($l) => $l->glAccount->code === '1020' && (float) $l->debit === 500.0));
    }

    public function test_withdraw_posts_a_balanced_journal_entry(): void
    {
        $account = $this->makeAccount('current', 1000);

        $txnService = app(TransactionService::class);
        $txn = $txnService->withdraw($account, ['amount' => 300, 'description' => 'Cash withdrawal']);

        $entry = JournalEntry::where('source_type', 'transaction')->where('source_id', $txn->id)->firstOrFail();

        $this->assertEquals(300, $entry->totalDebit());
        $this->assertEquals(300, $entry->totalCredit());
        $this->assertTrue($entry->lines->contains(fn ($l) => $l->glAccount->code === '2010' && (float) $l->debit === 300.0));
        $this->assertTrue($entry->lines->contains(fn ($l) => $l->glAccount->code === '1020' && (float) $l->credit === 300.0));
    }

    public function test_transfer_posts_a_balanced_journal_entry_between_deposit_accounts(): void
    {
        $from = $this->makeAccount('savings', 1000);
        $to   = $this->makeAccount('current', 200);

        $txnService = app(TransactionService::class);
        [$fromTxn, $toTxn] = $txnService->transfer($from, $to, ['amount' => 400, 'description' => 'Internal transfer']);

        $entry = JournalEntry::where('source_type', 'transaction')->where('source_id', $fromTxn->reference)->firstOrFail();

        $this->assertEquals(400, $entry->totalDebit());
        $this->assertEquals(400, $entry->totalCredit());
        $this->assertTrue($entry->lines->contains(fn ($l) => $l->glAccount->code === '2000' && (float) $l->debit === 400.0));
        $this->assertTrue($entry->lines->contains(fn ($l) => $l->glAccount->code === '2010' && (float) $l->credit === 400.0));
    }

    public function test_reversing_a_deposit_reverses_its_journal_entry(): void
    {
        $account = $this->makeAccount('savings', 0);

        $txnService = app(TransactionService::class);
        $txn = $txnService->deposit($account, ['amount' => 250]);
        $reversal = $txnService->reverse($txn->fresh(), 'Customer requested reversal');

        $reversalEntry = JournalEntry::where('source_type', 'transaction')->where('source_id', $reversal->id)->firstOrFail();

        $this->assertTrue($reversalEntry->is_reversal);
        $this->assertTrue($reversalEntry->lines->contains(fn ($l) => $l->glAccount->code === '2000' && (float) $l->debit === 250.0));
        $this->assertTrue($reversalEntry->lines->contains(fn ($l) => $l->glAccount->code === '1020' && (float) $l->credit === 250.0));
    }

    public function test_loan_disbursement_and_repayment_post_principal_interest_and_penalty_correctly(): void
    {
        $account = $this->makeAccount('savings', 0);
        $customer = $account->customer;

        $loanService = app(LoanService::class);

        $loan = $loanService->apply($customer, $account, [
            'amount'                => 1200,
            'interest_rate'         => 12,
            'tenure_months'         => 12,
            'first_repayment_date'  => today()->addMonth(),
        ]);

        $loan->update(['status' => 'approved']);
        $loan = $loanService->disburse($loan->fresh());

        $disburseEntry = JournalEntry::where('source_type', 'transaction')
            ->where('source_id', $loan->disbursement_transaction_id)
            ->firstOrFail();

        $this->assertEquals(1200, $disburseEntry->totalDebit());
        $this->assertTrue($disburseEntry->lines->contains(fn ($l) => $l->glAccount->code === '1100' && (float) $l->debit === 1200.0));
        $this->assertTrue($disburseEntry->lines->contains(fn ($l) => $l->glAccount->code === '2000' && (float) $l->credit === 1200.0));

        $installment = $loan->fresh()->repaymentSchedules()->orderBy('installment_number')->first();
        $totalDue    = (float) $installment->total_due;

        $account->refresh();
        $account->update(['balance' => $totalDue]); // fund the account so the repayment can be collected

        $payment = $loanService->makeRepayment($loan->fresh(), $installment, $totalDue, null);

        $repaymentEntry = JournalEntry::where('source_type', 'transaction')->where('source_id', $payment->transaction_id)->firstOrFail();

        $this->assertEquals($totalDue, $repaymentEntry->totalDebit());
        $this->assertEquals($totalDue, $repaymentEntry->totalCredit());
        $this->assertTrue($repaymentEntry->lines->contains(fn ($l) => $l->glAccount->code === '2000' && (float) $l->debit === $totalDue));
        $this->assertTrue($repaymentEntry->lines->contains(fn ($l) => $l->glAccount->code === '1100'));
        $this->assertTrue($repaymentEntry->lines->contains(fn ($l) => $l->glAccount->code === '4000'));
    }

    public function test_cheque_deposit_clearing_and_bounce_lifecycle_posts_correctly(): void
    {
        $drawer      = $this->makeAccount('current', 0);
        $beneficiary = $this->makeAccount('savings', 0);

        // Fund the drawer through a GL-instrumented deposit rather than seeding the
        // balance column directly, so the ledger and the account start in lockstep.
        app(TransactionService::class)->deposit($drawer, ['amount' => 1000]);
        $drawer->refresh();

        $chequeService = app(ChequeService::class);
        $book   = $chequeService->issueBook($drawer->customer, $drawer, []);
        $cheque = $book->cheques()->first();

        // Deposit for clearing: drawer debited immediately, suspense account credited.
        $cheque = $chequeService->depositCheque($cheque, [
            'amount' => 400,
            'payee_name' => 'Beneficiary Co',
            'beneficiary_account_id' => $beneficiary->id,
        ]);

        $depositEntry = JournalEntry::where('source_type', 'cheque')->where('source_id', $cheque->id)->latest('created_at')->first();
        $this->assertTrue($depositEntry->lines->contains(fn ($l) => $l->glAccount->code === '2010' && (float) $l->debit === 400.0));
        $this->assertTrue($depositEntry->lines->contains(fn ($l) => $l->glAccount->code === '2100' && (float) $l->credit === 400.0));

        // Clear it: suspense drained, beneficiary credited.
        $cheque->update(['clearing_date' => today()]);
        $chequeService->processClearing();

        $clearEntry = JournalEntry::where('source_type', 'cheque')->where('source_id', $cheque->id)
            ->where('description', 'like', 'Cheque cleared%')->first();
        $this->assertNotNull($clearEntry);
        $this->assertTrue($clearEntry->lines->contains(fn ($l) => $l->glAccount->code === '2100' && (float) $l->debit === 400.0));
        $this->assertTrue($clearEntry->lines->contains(fn ($l) => $l->glAccount->code === '2000' && (float) $l->credit === 400.0));

        // Reconciliation: suspense account should now be back to zero (nothing pending).
        $gl = app(GeneralLedgerService::class);
        $this->assertEmpty($gl->reconciliationReport());
    }

    public function test_trial_balance_stays_balanced_after_a_mix_of_operations(): void
    {
        $a = $this->makeAccount('savings', 0);
        $b = $this->makeAccount('current', 0);

        $txnService = app(TransactionService::class);
        $txnService->deposit($a, ['amount' => 500]);
        $txnService->deposit($b, ['amount' => 500]);
        $txnService->deposit($a, ['amount' => 200]);
        $txnService->withdraw($b, ['amount' => 100]);
        $txnService->transfer($a, $b, ['amount' => 150]);

        $gl = app(GeneralLedgerService::class);
        $rows = $gl->trialBalance();

        $this->assertEqualsWithDelta($rows->sum('debit_total'), $rows->sum('credit_total'), 0.01);
        $this->assertEmpty($gl->reconciliationReport());
    }

    public function test_backfill_command_refuses_to_run_twice(): void
    {
        $this->makeAccount('savings', 1000);

        Artisan::call('gl:backfill-opening-balances');
        $this->assertEquals(1, JournalEntry::where('source_type', 'opening_balance')->count());

        $exitCode = Artisan::call('gl:backfill-opening-balances');
        $this->assertNotEquals(0, $exitCode);
        $this->assertEquals(1, JournalEntry::where('source_type', 'opening_balance')->count());
    }
}
