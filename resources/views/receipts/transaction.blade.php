<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }
  .receipt { width: 340px; margin: 20px auto; border: 1px solid #ccc; border-radius: 6px; overflow: hidden; }

  .receipt-header { background: #fff; text-align: center; padding: 14px 12px 0; border-bottom: none; }
  .receipt-header .logo-wrap { margin-bottom: 6px; }
  .receipt-header .logo-wrap img { height: 62px; width: auto; }
  .receipt-header .bank { font-size: 15px; font-weight: bold; letter-spacing: .5px; color: #0B2447; }
  .receipt-header .branch { font-size: 9px; color: #888; margin-top: 2px; }
  .receipt-header .title { font-size: 11px; font-weight: bold; background: #0B2447; color: #fff; margin-top: 10px; padding: 7px 12px; text-transform: uppercase; letter-spacing: 1.5px; }

  .receipt-body { padding: 14px 16px; }
  .row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed #eee; }
  .row:last-child { border-bottom: none; }
  .row .label { color: #888; font-size: 10px; }
  .row .value { font-weight: 600; font-size: 10.5px; text-align: right; }

  .amount-block { background: #f0f4ff; border: 1px solid #c7d4f0; border-radius: 4px; padding: 12px; margin: 10px 0; text-align: center; }
  .amount-block .type { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #666; }
  .amount-block .amount { font-size: 22px; font-weight: bold; color: #0B2447; margin-top: 4px; }
  .amount-block .currency { font-size: 11px; color: #555; }

  .receipt-footer { background: #f8f9fa; border-top: 1px solid #eee; padding: 10px 16px; text-align: center; font-size: 9px; color: #aaa; }
  .status-ok { display: inline-block; background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
</style>
</head>
<body>
<div class="receipt">
  <div class="receipt-header">
    @php $logoPath = 'file://' . str_replace('\\', '/', public_path('images/logo.png')); @endphp
    @if(file_exists(public_path('images/logo.png')))
      <div class="logo-wrap">
        <img src="{{ $logoPath }}" alt="NABAAD Bank">
      </div>
    @else
      <div class="bank">NABAAD Bank</div>
      <div class="branch">Garowe Branch · Somalia</div>
    @endif
    <div class="title">Transaction Receipt</div>
  </div>

  <div class="receipt-body">
    <div class="amount-block">
      <div class="type">{{ ucfirst($transaction->type) }}</div>
      <div class="amount">{{ number_format($transaction->amount, 2) }}</div>
      <div class="currency">{{ $transaction->currency ?? 'USD' }}</div>
    </div>

    <div class="row">
      <span class="label">Reference</span>
      <span class="value" style="font-family:monospace;font-size:10px">{{ $transaction->reference }}</span>
    </div>
    <div class="row">
      <span class="label">Account</span>
      <span class="value" style="font-family:monospace">{{ $transaction->account?->account_number }}</span>
    </div>
    <div class="row">
      <span class="label">Account Holder</span>
      <span class="value">{{ $transaction->account?->customer?->name }}</span>
    </div>
    @if($transaction->relatedAccount)
    <div class="row">
      <span class="label">To Account</span>
      <span class="value" style="font-family:monospace">{{ $transaction->relatedAccount->account_number }}</span>
    </div>
    @endif
    <div class="row">
      <span class="label">Balance Before</span>
      <span class="value">USD {{ number_format($transaction->balance_before, 2) }}</span>
    </div>
    <div class="row">
      <span class="label">Balance After</span>
      <span class="value" style="color:#0B2447">USD {{ number_format($transaction->balance_after, 2) }}</span>
    </div>
    <div class="row">
      <span class="label">Description</span>
      <span class="value" style="max-width:180px;text-align:right">{{ $transaction->description }}</span>
    </div>
    <div class="row">
      <span class="label">Date & Time</span>
      <span class="value">{{ \Carbon\Carbon::parse($transaction->completed_at ?? $transaction->created_at)->format('d M Y, H:i') }}</span>
    </div>
    <div class="row">
      <span class="label">Processed By</span>
      <span class="value">{{ $transaction->processedBy?->name ?? 'System' }}</span>
    </div>
    <div class="row">
      <span class="label">Status</span>
      <span class="value"><span class="status-ok">{{ strtoupper($transaction->status) }}</span></span>
    </div>
  </div>

  <div class="receipt-footer">
    Thank you for banking with NABAAD Bank<br>
    This is a computer-generated receipt. No signature required.
  </div>
</div>
</body>
</html>
