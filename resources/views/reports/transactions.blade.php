@extends('reports.layout')
@section('report-title', 'Transaction Report')
@section('content')

<div class="filters">
  <span><strong>Period:</strong> {{ $dateFrom }} — {{ $dateTo }}</span>
  @if($accountFilter)<span><strong>Account:</strong> {{ $accountFilter }}</span>@endif
  @if($typeFilter)<span><strong>Type:</strong> {{ ucfirst($typeFilter) }}</span>@endif
  <span><strong>Total Records:</strong> {{ $transactions->count() }}</span>
</div>

<!-- Summary -->
<div class="summary">
  <div class="summary-card">
    <div class="label">Total Deposits</div>
    <div class="value">USD {{ number_format($totals['deposits'], 2) }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Total Withdrawals</div>
    <div class="value">USD {{ number_format($totals['withdrawals'], 2) }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Total Transfers</div>
    <div class="value">USD {{ number_format($totals['transfers'], 2) }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Net Flow</div>
    <div class="value" style="color:{{ $totals['net'] >= 0 ? '#065f46' : '#991b1b' }}">
      USD {{ number_format(abs($totals['net']), 2) }} {{ $totals['net'] >= 0 ? '▲' : '▼' }}
    </div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Date</th>
      <th>Reference</th>
      <th>Account</th>
      <th>Customer</th>
      <th>Type</th>
      <th class="r">Amount (USD)</th>
      <th class="r">Balance After</th>
      <th>Status</th>
      <th>By</th>
    </tr>
  </thead>
  <tbody>
    @forelse($transactions as $txn)
    <tr>
      <td>{{ \Carbon\Carbon::parse($txn->created_at)->format('d M Y') }}</td>
      <td style="font-family:monospace;font-size:9px">{{ $txn->reference }}</td>
      <td style="font-family:monospace;font-size:9px">{{ $txn->account?->account_number }}</td>
      <td>{{ $txn->account?->customer?->name }}</td>
      <td>
        <span class="badge badge-{{ match($txn->type) { 'deposit'=>'success', 'withdrawal'=>'danger', 'transfer'=>'primary', 'reversal'=>'warning', default=>'secondary' } }}">
          {{ ucfirst($txn->type) }}
        </span>
      </td>
      <td class="r">{{ number_format($txn->amount, 2) }}</td>
      <td class="r">{{ number_format($txn->balance_after, 2) }}</td>
      <td>
        <span class="badge badge-{{ $txn->status === 'completed' ? 'success' : 'warning' }}">
          {{ $txn->status }}
        </span>
      </td>
      <td class="muted">{{ $txn->processedBy?->name }}</td>
    </tr>
    @empty
    <tr><td colspan="9" style="text-align:center;color:#aaa;padding:20px">No transactions found for the selected filters.</td></tr>
    @endforelse
    @if($transactions->count())
    <tr class="totals-row">
      <td colspan="5">TOTALS</td>
      <td class="r">USD {{ number_format($totals['all'], 2) }}</td>
      <td colspan="3"></td>
    </tr>
    @endif
  </tbody>
</table>
@endsection
