@extends('reports.layout')
@section('report-title', $title ?? 'Trial Balance')
@section('content')

<div class="filters">
  <span><strong>As Of:</strong> {{ $asOf }}</span>
  <span><strong>Accounts:</strong> {{ $rows->count() }}</span>
</div>

<table>
  <thead>
    <tr>
      <th>Code</th>
      <th>Account</th>
      <th>Type</th>
      <th class="r">Debit</th>
      <th class="r">Credit</th>
      <th class="r">Balance</th>
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $row)
    <tr>
      <td style="font-family:monospace">{{ $row['code'] }}</td>
      <td>{{ $row['name'] }}</td>
      <td class="muted">{{ ucfirst($row['type']) }}</td>
      <td class="r">{{ number_format($row['debit_total'], 2) }}</td>
      <td class="r">{{ number_format($row['credit_total'], 2) }}</td>
      <td class="r" style="font-weight:bold">{{ number_format($row['balance'], 2) }}</td>
    </tr>
    @empty
    <tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No GL activity found.</td></tr>
    @endforelse
    @if($rows->count())
    <tr class="totals-row">
      <td colspan="3">TOTALS</td>
      <td class="r">{{ number_format($rows->sum('debit_total'), 2) }}</td>
      <td class="r">{{ number_format($rows->sum('credit_total'), 2) }}</td>
      <td></td>
    </tr>
    @endif
  </tbody>
</table>
@endsection
