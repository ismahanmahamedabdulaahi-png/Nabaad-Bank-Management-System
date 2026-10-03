@extends('reports.layout')
@section('report-title', 'Teller Till Summary')
@section('content')

<div class="filters">
  <span><strong>Date:</strong> {{ $date }}</span>
  @if($teller)<span><strong>Teller:</strong> {{ $teller }}</span>@endif
</div>

@foreach($tills as $till)
<div style="margin-bottom:18px;border:1px solid #d8e2f0;border-radius:4px;overflow:hidden">
  <!-- Till header -->
  <div style="background:#0B2447;color:#fff;padding:7px 12px;display:flex;justify-content:space-between">
    <span style="font-weight:bold">{{ $till->till_name }}</span>
    <span style="font-size:9px;opacity:.8">Teller: {{ $till->teller?->name }} &nbsp;|&nbsp; Status: {{ strtoupper($till->status) }}</span>
  </div>
  <div style="display:flex;gap:0">
    <div style="flex:1;padding:8px 12px;border-right:1px solid #e8edf5">
      <div style="font-size:9px;color:#888">Opening Balance</div>
      <div style="font-weight:bold;font-size:12px">USD {{ number_format($till->opening_balance, 2) }}</div>
    </div>
    <div style="flex:1;padding:8px 12px;border-right:1px solid #e8edf5">
      <div style="font-size:9px;color:#888">Expected Balance</div>
      <div style="font-weight:bold;font-size:12px">USD {{ number_format($till->expected_balance, 2) }}</div>
    </div>
    <div style="flex:1;padding:8px 12px;border-right:1px solid #e8edf5">
      <div style="font-size:9px;color:#888">Closing Balance</div>
      <div style="font-weight:bold;font-size:12px">USD {{ number_format($till->closing_balance ?? $till->current_balance, 2) }}</div>
    </div>
    <div style="flex:1;padding:8px 12px">
      <div style="font-size:9px;color:#888">Variance</div>
      @php $var = ($till->closing_balance ?? $till->current_balance) - $till->expected_balance; @endphp
      <div style="font-weight:bold;font-size:12px;color:{{ abs($var) < 0.01 ? '#065f46' : '#991b1b' }}">
        {{ $var >= 0 ? '+' : '' }}USD {{ number_format($var, 2) }}
      </div>
    </div>
  </div>

  @if($till->movements->count())
  <table style="margin:0">
    <thead>
      <tr>
        <th>Time</th>
        <th>Movement Type</th>
        <th class="r">Amount (USD)</th>
        <th>Notes</th>
        <th>By</th>
      </tr>
    </thead>
    <tbody>
      @foreach($till->movements as $mv)
      <tr>
        <td class="muted">{{ \Carbon\Carbon::parse($mv->created_at)->format('H:i') }}</td>
        <td>{{ ucwords(str_replace('_', ' ', $mv->type)) }}</td>
        <td class="r" style="color:{{ in_array($mv->type, ['replenishment','transfer_in']) ? '#065f46' : '#991b1b' }}">
          {{ in_array($mv->type, ['replenishment','transfer_in']) ? '+' : '-' }}{{ number_format($mv->amount, 2) }}
        </td>
        <td class="muted">{{ $mv->notes }}</td>
        <td class="muted">{{ $mv->processedBy?->name }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
</div>
@endforeach
@endsection
