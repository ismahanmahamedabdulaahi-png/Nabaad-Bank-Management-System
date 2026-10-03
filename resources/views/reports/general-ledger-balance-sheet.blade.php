@extends('reports.layout')
@section('report-title', 'Balance Sheet')
@section('content')

<div class="filters">
  <span><strong>As Of:</strong> {{ $asOf }}</span>
</div>

<div class="summary">
  <div class="summary-card">
    <div class="label">Total Assets</div>
    <div class="value">USD {{ number_format($sheet['total_assets'], 2) }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Total Liabilities</div>
    <div class="value">USD {{ number_format($sheet['total_liabilities'], 2) }}</div>
  </div>
  <div class="summary-card">
    <div class="label">Total Equity</div>
    <div class="value">USD {{ number_format($sheet['total_equity'], 2) }}</div>
  </div>
</div>

<table>
  <thead><tr><th colspan="2">Assets</th></tr></thead>
  <tbody>
    @foreach($sheet['assets'] as $row)
    <tr><td>{{ $row['code'] }} — {{ $row['name'] }}</td><td class="r">{{ number_format($row['balance'], 2) }}</td></tr>
    @endforeach
    <tr class="totals-row"><td>TOTAL ASSETS</td><td class="r">{{ number_format($sheet['total_assets'], 2) }}</td></tr>
  </tbody>
</table>

<table style="margin-top:16px">
  <thead><tr><th colspan="2">Liabilities</th></tr></thead>
  <tbody>
    @foreach($sheet['liabilities'] as $row)
    <tr><td>{{ $row['code'] }} — {{ $row['name'] }}</td><td class="r">{{ number_format($row['balance'], 2) }}</td></tr>
    @endforeach
    <tr class="totals-row"><td>TOTAL LIABILITIES</td><td class="r">{{ number_format($sheet['total_liabilities'], 2) }}</td></tr>
  </tbody>
</table>

<table style="margin-top:16px">
  <thead><tr><th colspan="2">Equity</th></tr></thead>
  <tbody>
    @foreach($sheet['equity'] as $row)
    <tr><td>{{ $row['code'] }} — {{ $row['name'] }}</td><td class="r">{{ number_format($row['balance'], 2) }}</td></tr>
    @endforeach
    <tr class="totals-row"><td>TOTAL EQUITY</td><td class="r">{{ number_format($sheet['total_equity'], 2) }}</td></tr>
  </tbody>
</table>
@endsection
