@extends('reports.layout')
@section('report-title', 'Loan Portfolio Report')
@section('content')

<div class="filters">
  <span><strong>Period:</strong> {{ $dateFrom }} — {{ $dateTo }}</span>
  @if($statusFilter)<span><strong>Status:</strong> {{ ucfirst($statusFilter) }}</span>@endif
  <span><strong>Total Loans:</strong> {{ $loans->count() }}</span>
</div>

<div class="summary">
  <div class="summary-card">
    <div class="label">Total Disbursed</div>
    <div class="value">USD {{ number_format($totals['disbursed'], 2) }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Outstanding</div>
    <div class="value">USD {{ number_format($totals['outstanding'], 2) }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Total Collected</div>
    <div class="value" style="color:#065f46">USD {{ number_format($totals['paid'], 2) }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Overdue Loans</div>
    <div class="value" style="color:#991b1b">{{ $totals['overdue_count'] }}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Loan #</th>
      <th>Customer</th>
      <th>Account</th>
      <th class="r">Amount</th>
      <th>Rate</th>
      <th>Tenure</th>
      <th class="r">Outstanding</th>
      <th class="r">Total Paid</th>
      <th>Status</th>
      <th>Disbursed</th>
    </tr>
  </thead>
  <tbody>
    @forelse($loans as $loan)
    <tr>
      <td style="font-family:monospace;font-size:9px">{{ $loan->loan_number }}</td>
      <td>{{ $loan->customer?->name }}</td>
      <td style="font-family:monospace;font-size:9px">{{ $loan->account?->account_number }}</td>
      <td class="r">{{ number_format($loan->amount, 2) }}</td>
      <td>{{ $loan->interest_rate }}%</td>
      <td>{{ $loan->tenure_months }}m</td>
      <td class="r" style="color:#991b1b;font-weight:bold">{{ number_format($loan->outstanding_balance, 2) }}</td>
      <td class="r" style="color:#065f46">{{ number_format($loan->total_paid, 2) }}</td>
      <td>
        <span class="badge badge-{{ match($loan->status) {
          'active'=>'primary', 'closed'=>'success', 'overdue'=>'danger',
          'defaulted'=>'danger', 'pending_approval'=>'warning', default=>'secondary'
        } }}">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span>
      </td>
      <td class="muted">{{ $loan->disbursed_at ? \Carbon\Carbon::parse($loan->disbursed_at)->format('d M Y') : '—' }}</td>
    </tr>
    @empty
    <tr><td colspan="10" style="text-align:center;color:#aaa;padding:20px">No loans found.</td></tr>
    @endforelse
    @if($loans->count())
    <tr class="totals-row">
      <td colspan="3">TOTALS</td>
      <td class="r">{{ number_format($totals['disbursed'], 2) }}</td>
      <td colspan="2"></td>
      <td class="r">{{ number_format($totals['outstanding'], 2) }}</td>
      <td class="r">{{ number_format($totals['paid'], 2) }}</td>
      <td colspan="2"></td>
    </tr>
    @endif
  </tbody>
</table>
@endsection
