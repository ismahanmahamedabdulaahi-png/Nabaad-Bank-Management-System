<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1a1a1a; }
  .page { padding: 32px 40px; }
  .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0B2447; padding-bottom: 12px; margin-bottom: 20px; }
  .logo-wrap img { height: 64px; width: auto; }
  .bank-name { font-size: 20px; font-weight: bold; color: #0B2447; }
  .bank-sub  { font-size: 10px; color: #666; }
  .doc-info  { text-align: right; font-size: 10px; color: #666; }
  .doc-info strong { color: #0B2447; font-size: 12px; display: block; }

  h2 { font-size: 14px; text-align: center; text-transform: uppercase; letter-spacing: 1px; margin: 16px 0; color: #0B2447; border-bottom: 1px solid #ccc; padding-bottom: 8px; }

  table.info { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  table.info td { padding: 5px 8px; font-size: 10.5px; }
  table.info td:first-child { color: #888; width: 40%; }
  table.info td:last-child { font-weight: 600; }
  table.info tr:nth-child(even) { background: #f8fafc; }

  .amount-box { border: 2px solid #0B2447; border-radius: 4px; padding: 14px 20px; text-align: center; margin: 16px 0; }
  .amount-box .label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
  .amount-box .amount { font-size: 26px; font-weight: bold; color: #0B2447; margin: 4px 0; }
  .amount-box .words { font-size: 10px; color: #555; font-style: italic; }

  .terms { background: #f8fafc; border: 1px solid #dde; border-radius: 4px; padding: 10px 14px; margin-top: 12px; font-size: 9.5px; color: #555; }
  .terms ul { padding-left: 16px; margin-top: 4px; }
  .terms li { margin-bottom: 3px; }

  .signature { display: flex; justify-content: space-between; margin-top: 40px; }
  .sig-block { text-align: center; width: 180px; }
  .sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; font-size: 9px; color: #666; }

  .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #aaa; border-top: 1px solid #eee; padding-top: 8px; }
</style>
</head>
<body>
<div class="page">
  <div class="header">
    <div class="logo-wrap">
      @php $logoPath = 'file://' . str_replace('\\', '/', public_path('images/logo.png')); @endphp
      @if(file_exists(public_path('images/logo.png')))
        <img src="{{ $logoPath }}" alt="NABAAD Bank">
      @else
        <div class="bank-name">NABAAD Bank</div>
        <div class="bank-sub">Garowe Branch · Somalia</div>
      @endif
    </div>
    <div class="doc-info">
      <strong>Loan Disbursement Letter</strong>
      Date: {{ now()->format('d M Y') }}<br>
      Ref: {{ $loan->loan_number }}
    </div>
  </div>

  <h2>Loan Disbursement Confirmation</h2>

  <table class="info">
    <tr><td>Customer Name</td><td>{{ $loan->customer?->name }}</td></tr>
    <tr><td>Customer ID</td><td>{{ $loan->customer?->customer_number }}</td></tr>
    <tr><td>Account Number</td><td>{{ $loan->account?->account_number }}</td></tr>
    <tr><td>Loan Number</td><td>{{ $loan->loan_number }}</td></tr>
    <tr><td>Loan Purpose</td><td>{{ $loan->purpose ?? 'General' }}</td></tr>
    <tr><td>Disbursement Date</td><td>{{ $loan->disbursed_at ? \Carbon\Carbon::parse($loan->disbursed_at)->format('d M Y') : now()->format('d M Y') }}</td></tr>
  </table>

  <div class="amount-box">
    <div class="label">Loan Amount Disbursed</div>
    <div class="amount">USD {{ number_format($loan->amount, 2) }}</div>
  </div>

  <table class="info">
    <tr><td>Interest Rate</td><td>{{ $loan->interest_rate }}% per annum</td></tr>
    <tr><td>Tenure</td><td>{{ $loan->tenure_months }} months</td></tr>
    <tr><td>Monthly EMI</td><td>USD {{ number_format($loan->monthly_emi, 2) }}</td></tr>
    <tr><td>Total Payable</td><td>USD {{ number_format($loan->total_payable, 2) }}</td></tr>
    <tr><td>Total Interest</td><td>USD {{ number_format($loan->total_interest, 2) }}</td></tr>
    <tr><td>First Repayment</td><td>{{ $loan->first_repayment_date ? \Carbon\Carbon::parse($loan->first_repayment_date)->format('d M Y') : '—' }}</td></tr>
    <tr><td>Grace Period</td><td>{{ $loan->grace_period_days ?? 0 }} days</td></tr>
  </table>

  <div class="terms">
    <strong>Terms & Conditions:</strong>
    <ul>
      <li>Repayments are due on the same date each month starting from the first repayment date.</li>
      <li>Late payments incur a penalty rate of {{ $loan->penalty_rate ?? 2 }}% per month on the overdue amount.</li>
      <li>Early repayment is permitted without penalty after 3 months.</li>
      <li>Collateral: {{ $loan->collateral ?? 'None specified' }}.</li>
    </ul>
  </div>

  <div class="signature">
    <div class="sig-block">
      <div class="sig-line">Customer Signature<br>{{ $loan->customer?->name }}</div>
    </div>
    <div class="sig-block">
      <div class="sig-line">Authorized Officer<br>NABAAD Bank</div>
    </div>
  </div>

  <div class="footer">
    NABAAD Bank · Trust &bull; Security &bull; Progress &bull; Garowe Branch, Somalia<br>
    This document is computer-generated and valid without a physical stamp.
  </div>
</div>
</body>
</html>
