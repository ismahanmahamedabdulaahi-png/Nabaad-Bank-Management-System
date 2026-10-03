<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }
  .page { padding: 24px 28px; }

  /* Header */
  .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0B2447; padding-bottom: 10px; margin-bottom: 16px; }
  .logo-wrap img { height: 52px; width: auto; }
  .logo-fallback { font-size: 18px; font-weight: bold; color: #0B2447; }
  .bank-sub  { font-size: 10px; color: #666; margin-top: 2px; }
  .report-title { text-align: right; }
  .report-title h2 { font-size: 13px; font-weight: bold; color: #0B2447; }
  .report-title .meta { font-size: 9px; color: #888; margin-top: 3px; }

  /* Filters row */
  .filters { background: #f4f7fc; border: 1px solid #d8e2f0; border-radius: 4px; padding: 7px 12px; margin-bottom: 14px; font-size: 9.5px; color: #555; }
  .filters span { margin-right: 16px; }
  .filters strong { color: #333; }

  /* Table */
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  thead tr { background: #0B2447; color: #fff; }
  thead th { padding: 6px 8px; text-align: left; font-size: 9.5px; font-weight: 600; }
  thead th.r { text-align: right; }
  tbody tr { border-bottom: 1px solid #e8edf5; }
  tbody tr:nth-child(even) { background: #f9fafc; }
  tbody td { padding: 5px 8px; font-size: 10px; vertical-align: top; }
  tbody td.r { text-align: right; font-family: monospace; }
  tbody td.muted { color: #888; }

  /* Totals */
  .totals-row td { font-weight: bold; background: #eef2fa; border-top: 2px solid #0B2447; }

  /* Summary cards */
  .summary { display: flex; gap: 10px; margin-bottom: 14px; }
  .summary-card { flex: 1; border: 1px solid #d8e2f0; border-radius: 4px; padding: 8px 10px; background: #f9fafc; }
  .summary-card .label { font-size: 9px; color: #888; text-transform: uppercase; letter-spacing: .5px; }
  .summary-card .value { font-size: 14px; font-weight: bold; color: #0B2447; margin-top: 2px; }

  /* Status badges */
  .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8.5px; font-weight: 600; }
  .badge-success  { background: #d1fae5; color: #065f46; }
  .badge-danger   { background: #fee2e2; color: #991b1b; }
  .badge-warning  { background: #fef3c7; color: #92400e; }
  .badge-primary  { background: #dbeafe; color: #1e40af; }
  .badge-secondary{ background: #f1f5f9; color: #475569; }

  /* Footer */
  .footer { border-top: 1px solid #d8e2f0; margin-top: 16px; padding-top: 8px; display: flex; justify-content: space-between; font-size: 9px; color: #aaa; }
</style>
</head>
<body>
<div class="page">
  <!-- Header -->
  <div class="header">
    <div class="logo-wrap">
      @php $logoPath = 'file://' . str_replace('\\', '/', public_path('images/logo.png')); @endphp
      @if(file_exists(public_path('images/logo.png')))
        <img src="{{ $logoPath }}" alt="NABAAD Bank">
      @else
        <div class="logo-fallback">NABAAD Bank</div>
        <div class="bank-sub">Garowe Branch · Somalia</div>
      @endif
    </div>
    <div class="report-title">
      <h2>@yield('report-title')</h2>
      <div class="meta">Generated: {{ now()->format('d M Y, H:i') }}</div>
      <div class="meta">By: {{ auth()->user()?->name ?? 'System' }}</div>
    </div>
  </div>

  @yield('content')

  <div class="footer">
    <span>NABAAD Bank · Trust &bull; Security &bull; Progress — Confidential</span>
    <span>Page 1</span>
    <span>{{ now()->format('d/m/Y H:i:s') }}</span>
  </div>
</div>
</body>
</html>
