@extends('reports.layout')
@section('report-title', 'Cheque Register Report')
@section('content')

<div class="filters">
  <span><strong>Period:</strong> {{ $dateFrom }} — {{ $dateTo }}</span>
  @if($statusFilter)<span><strong>Status:</strong> {{ ucfirst(str_replace('_',' ',$statusFilter)) }}</span>@endif
  <span><strong>Total Cheques:</strong> {{ $cheques->count() }}</span>
</div>

<div class="summary">
  <div class="summary-card">
    <div class="label">Issued</div>
    <div class="value">{{ $stats['issued'] }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Paid (Cash)</div>
    <div class="value" style="color:#065f46">{{ $stats['paid'] }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Deposited</div>
    <div class="value" style="color:#1e40af">{{ $stats['deposited'] }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Bounced</div>
    <div class="value" style="color:#991b1b">{{ $stats['bounced'] }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Cancelled</div>
    <div class="value" style="color:#475569">{{ $stats['cancelled'] }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Total Value</div>
    <div class="value">USD {{ number_format($stats['total_value'], 2) }}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Cheque #</th>
      <th>Account</th>
      <th>Customer</th>
      <th>Payee</th>
      <th class="r">Amount</th>
      <th>Status</th>
      <th>Settlement</th>
      <th>Date</th>
      <th>By</th>
    </tr>
  </thead>
  <tbody>
    @forelse($cheques as $chq)
    <tr>
      <td style="font-family:monospace;font-weight:bold">{{ $chq->cheque_number }}</td>
      <td style="font-family:monospace;font-size:9px">{{ $chq->account?->account_number }}</td>
      <td>{{ $chq->account?->customer?->name }}</td>
      <td>{{ $chq->payee_name ?? '—' }}</td>
      <td class="r">{{ $chq->amount ? number_format($chq->amount, 2) : '—' }}</td>
      <td>
        <span class="badge badge-{{ match($chq->status) {
          'paid'=>'success', 'deposited'=>'primary', 'issued'=>'secondary',
          'bounced'=>'danger', 'cancelled'=>'secondary', default=>'secondary'
        } }}">{{ ucfirst(str_replace('_',' ',$chq->status)) }}</span>
      </td>
      <td class="muted">{{ $chq->settlement_type ? ucfirst(str_replace('_',' ',$chq->settlement_type)) : '—' }}</td>
      <td class="muted">{{ $chq->cleared_at ? \Carbon\Carbon::parse($chq->cleared_at)->format('d M Y') : ($chq->issue_date ? \Carbon\Carbon::parse($chq->issue_date)->format('d M Y') : '—') }}</td>
      <td class="muted">{{ $chq->processedBy?->name ?? '—' }}</td>
    </tr>
    @empty
    <tr><td colspan="9" style="text-align:center;color:#aaa;padding:20px">No cheques found.</td></tr>
    @endforelse
  </tbody>
</table>
@endsection
