<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  @font-face {
    font-family: 'IBM Plex Mono';
    font-weight: 400;
    src: url('{{ 'file://' . str_replace('\\', '/', public_path('fonts/IBMPlexMono-Regular.ttf')) }}') format('truetype');
  }
  @font-face {
    font-family: 'IBM Plex Mono';
    font-weight: 500;
    src: url('{{ 'file://' . str_replace('\\', '/', public_path('fonts/IBMPlexMono-Medium.ttf')) }}') format('truetype');
  }

  :root {
    --navy: #0F1C2E;
    --blue: #1D6FA4;
    --text: #1a1a1a;
    --text-muted: #555;
    --text-faint: #999;
    --border: #d0d0d0;
    --border-light: #e8e8e8;
    --green: #1a7a45;
    --red: #b32020;
  }

  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size:12px; color:var(--text); background:#fff; }
  .mono { font-family:'IBM Plex Mono', DejaVu Sans Mono, monospace; }

  /* Layout tables — dompdf's most reliable primitive for multi-column alignment */
  table.layout { width:100%; border-collapse:collapse; }
  table.layout td { vertical-align:top; }

  /* Label : value pairs */
  .field-row { margin-bottom:5px; white-space:nowrap; }
  .field-row:last-child { margin-bottom:0; }
  .fl { font-size:11px; color:var(--text-muted); margin-right:6px; }
  .fv { font-size:12px; font-weight:bold; color:var(--text); border-bottom:0.5px solid var(--border); padding-bottom:1px; }

  .top-meta { padding:16px 24px 10px; }
  .meta-col-left { width:32%; }
  .meta-col-logo { width:36%; text-align:center; }
  .meta-col-right { width:32%; text-align:right; }

  .logo-inner img { height:26px; vertical-align:middle; margin-right:7px; }
  .logo-name { font-size:18px; font-weight:bold; color:var(--navy); vertical-align:middle; }
  .logo-sub { font-size:10px; color:var(--text-muted); margin-top:3px; }

  .rule-thin { height:0.5px; background:var(--border); margin:8px 24px 0; }
  .rule-thick { height:2.5px; background:var(--navy); margin:0 24px; }
  .stmt-label { text-align:center; padding:6px 24px; font-size:11px; font-weight:bold; color:var(--text); letter-spacing:1.5px; text-transform:uppercase; border-bottom:0.5px solid var(--border); }

  /* Account info */
  .info-grid td { padding:10px 24px; }
  .info-col-right { border-left:0.5px solid var(--border); text-align:right; }
  .info-grid { border-bottom:0.5px solid var(--border); }
  .il { font-size:11px; color:var(--text-muted); margin-right:6px; }
  .iv { font-size:12px; font-weight:bold; color:var(--text); border-bottom:0.5px solid var(--border); padding-bottom:1px; }

  .cust-bar { padding:7px 24px; border-bottom:1px solid var(--border); background:#fafafa; }
  .cust-label { font-size:11px; color:var(--text-muted); }
  .cust-val { font-size:13px; font-weight:bold; color:var(--text); }

  /* Transactions table */
  table.txns { width:100%; border-collapse:collapse; }
  table.txns thead th { background:var(--navy); color:#E6F1FB; font-size:8.5px; font-weight:bold; text-transform:uppercase; letter-spacing:.2px; padding:7px 6px; text-align:left; white-space:nowrap; }
  table.txns thead th.r { text-align:right; }
  table.txns thead th:first-child { padding-left:24px; }
  table.txns thead th:last-child { padding-right:24px; }
  table.txns tbody td { padding:6px 6px; border-bottom:0.5px solid var(--border-light); font-size:10px; vertical-align:middle; white-space:nowrap; }
  table.txns tbody td.desc { white-space:normal; }
  table.txns tbody tr:nth-child(even) td { background:#fafafa; }
  table.txns tbody td:first-child { padding-left:24px; }
  table.txns tbody td:last-child { padding-right:24px; font-weight:bold; }
  table.txns tbody td.r { text-align:right; }
  .cr { color:var(--green); }
  .dr { color:var(--red); }
  .dash { color:var(--text-faint); }

  /* Totals bar */
  .totals-bar { border-top:1px solid var(--border); border-bottom:0.5px solid var(--border); }
  .totals-bar td { padding:7px 12px; border-left:0.5px solid var(--border); }
  .totals-bar td.info { border-left:none; padding:8px 24px; font-size:10.5px; color:var(--text-muted); vertical-align:middle; }
  .totals-bar td:last-child { padding-right:24px; }
  .tl { font-size:9px; color:var(--text-muted); margin-bottom:2px; text-transform:uppercase; letter-spacing:.3px; }
  .tv { font-size:12.5px; font-weight:bold; color:var(--text); }
  .tv.cr { color:var(--green); }
  .tv.dr { color:var(--red); }

  /* Signatures */
  .sig-section { padding:16px 24px 12px; page-break-inside:avoid; }
  .sig-section td { vertical-align:middle; }
  .sig-col { width:38%; }
  .sig-col.right { text-align:right; }
  .seal-col { width:24%; text-align:center; }
  .sig-lbl { font-size:9px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; }
  .sig-name { font-size:11.5px; font-weight:bold; color:var(--text); margin-top:3px; }
  .sig-role { font-size:9.5px; color:var(--text-muted); margin-top:1px; }
  .sig-line { border-bottom:0.5px solid var(--border); padding-bottom:3px; margin-top:9px; min-height:16px; }
  .sig-cursive { font-family: Georgia, serif; font-size:13px; color:var(--text); font-style:italic; opacity:.75; }
  .sig-date { font-size:9px; color:var(--text-muted); margin-top:3px; }

  /* CSS-only certification seal (no SVG — dompdf's SVG text layout is unreliable) */
  .seal { width:88px; height:88px; border:2px solid var(--navy); border-radius:50%; margin:0 auto; padding:5px; }
  .seal-ring { width:100%; height:100%; border:0.6px solid var(--navy); border-radius:50%; padding-top:11px; }
  .seal-bank { font-size:6.5px; font-weight:bold; letter-spacing:.5px; color:var(--navy); }
  .seal-branch { font-size:5.5px; letter-spacing:.5px; color:var(--navy); margin-top:2px; }
  .seal-word { font-size:9px; font-weight:bold; color:var(--navy); margin-top:5px; }
  .seal-word2 { font-size:6.5px; color:var(--navy); margin-top:1px; }
  .seal-date { font-size:5.5px; color:var(--navy); margin-top:5px; border-top:0.5px solid var(--navy); padding-top:3px; width:60px; margin-left:auto; margin-right:auto; }

  /* Notice */
  .notice { margin:0 24px 12px; padding:8px 12px; background:#f2f4f7; border-left:2.5px solid var(--blue); font-size:9.5px; color:var(--text-muted); line-height:1.5; }

  /* Footer */
  .doc-footer { background:var(--navy); padding:8px 24px; }
  .doc-footer td { vertical-align:middle; }
  .doc-footer .fn { font-size:9.5px; color:#85B7EB; }
  .doc-footer .fb { font-size:9.5px; color:#85B7EB; text-align:right; }
</style>
</head>
<body>

  @php
    $logoPath = 'file://' . str_replace('\\', '/', public_path('images/logo-email.png'));
    $logoExists = file_exists(public_path('images/logo-email.png'));
    $branch = $account->branch;
    $officer = $account->openedBy;
    $officerRole = $officer?->roles->first()?->name ?? 'Customer Relations Officer';
    $manager = $branch?->manager;
    $statementRef = 'STMT-' . strtoupper(substr(md5($account->id . $from . $to), 0, 8));

    $credits = $transactions->whereIn('type', ['deposit', 'loan_disbursement', 'reversal'])->sum('amount');
    $debits  = $transactions->whereIn('type', ['withdrawal', 'loan_repayment', 'transfer'])->sum('amount');
    $openBal = $transactions->first()?->balance_before ?? $account->balance;
    $closeBal= $transactions->last()?->balance_after   ?? $account->balance;

    $signatureAbbrev = function (?string $name) {
        if (!$name) return null;
        $parts = preg_split('/\s+/', trim($name));
        $surname = array_pop($parts);
        $initials = collect($parts)->map(fn ($p) => mb_substr($p, 0, 1) . '.')->join(' ');
        return trim($initials . ' ' . $surname);
    };
    $officerSignature = $signatureAbbrev($officer->name ?? null);
    $managerSignature = $signatureAbbrev($manager->name ?? null);
  @endphp

  <!-- Top meta + logo -->
  <table class="layout top-meta">
    <tr>
      <td class="meta-col-left">
        <div class="field-row"><span class="fl">Date:</span><span class="fv">{{ now()->format('d M Y') }}</span></div>
        <div class="field-row"><span class="fl">Time:</span><span class="fv">{{ now()->format('H:i') }}</span></div>
        <div class="field-row"><span class="fl">Branch:</span><span class="fv">{{ $branch->name ?? '—' }}</span></div>
      </td>
      <td class="meta-col-logo">
        <div class="logo-inner">
          @if($logoExists)
            <img src="{{ $logoPath }}" alt="NABAAD Bank">
          @endif
          <span class="logo-name">NABAAD Bank</span>
        </div>
        <div class="logo-sub">Official account statement</div>
      </td>
      <td class="meta-col-right">
        <div class="field-row"><span class="fl">To date:</span><span class="fv">{{ \Carbon\Carbon::parse($to)->format('d M Y') }}</span></div>
        <div class="field-row"><span class="fl">Currency:</span><span class="fv">{{ $account->currency ?? 'USD' }}</span></div>
      </td>
    </tr>
  </table>

  <div class="rule-thin"></div>
  <div class="rule-thick"></div>
  <div class="stmt-label">Account Statement</div>

  <!-- Account info -->
  <table class="layout info-grid">
    <tr>
      <td>
        <div class="field-row"><span class="il">From date:</span><span class="iv">{{ \Carbon\Carbon::parse($from)->format('d M Y') }}</span></div>
        <div class="field-row"><span class="il">Acc number:</span><span class="iv mono">{{ $account->account_number }}</span></div>
        <div class="field-row"><span class="il">Acc name:</span><span class="iv">{{ $account->customer->name }}</span></div>
      </td>
      <td class="info-col-right">
        <div class="field-row"><span class="il">To date:</span><span class="iv">{{ \Carbon\Carbon::parse($to)->format('d M Y') }}</span></div>
        <div class="field-row"><span class="il">Currency:</span><span class="iv">{{ $account->currency ?? 'USD' }}</span></div>
      </td>
    </tr>
  </table>

  <div class="cust-bar">
    <span class="cust-label">Customer name:</span>
    <span class="cust-val">{{ $account->customer->name }}</span>
  </div>

  <!-- Transactions -->
  @if($transactions->isEmpty())
    <p style="text-align:center;color:#888;padding:20px 0;font-size:10px">No transactions in this period.</p>
  @else
  <table class="txns">
    <thead>
      <tr>
        <th style="width:10%">Trans date</th>
        <th class="mono" style="width:13%">Trans no.</th>
        <th style="width:29%">Description</th>
        <th style="width:12%">Trans branch</th>
        <th class="r" style="width:12%">Deposit</th>
        <th class="r" style="width:12%">Withdrawal</th>
        <th class="r" style="width:12%">Balance</th>
      </tr>
    </thead>
    <tbody>
      @foreach($transactions as $txn)
      @php
        $isDebit = in_array($txn->type, ['withdrawal', 'loan_repayment', 'transfer']);
        $isCredit= in_array($txn->type, ['deposit', 'loan_disbursement', 'reversal']);
      @endphp
      <tr>
        <td class="mono" style="color:var(--text-muted);font-size:9px">{{ \Carbon\Carbon::parse($txn->created_at)->format('d M Y') }}</td>
        <td class="mono" style="color:var(--text-muted);font-size:9px">{{ $txn->reference }}</td>
        <td class="desc">{{ Str::limit($txn->description ?? ucfirst($txn->type), 38) }}</td>
        <td style="color:var(--text-muted)">{{ $branch->name ?? '—' }}</td>
        <td class="r mono {{ $isCredit ? 'cr' : 'dash' }}">{{ $isCredit ? number_format($txn->amount, 2) : '—' }}</td>
        <td class="r mono {{ $isDebit ? 'dr' : 'dash' }}">{{ $isDebit ? number_format($txn->amount, 2) : '—' }}</td>
        <td class="r mono">{{ number_format($txn->balance_after, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  <!-- Totals -->
  <table class="layout totals-bar">
    <tr>
      <td class="info">{{ $transactions->count() }} transaction(s) in this period</td>
      <td><div class="tl">Opening</div><div class="tv mono">{{ number_format($openBal, 2) }}</div></td>
      <td><div class="tl">Total deposit</div><div class="tv cr mono">+{{ number_format($credits, 2) }}</div></td>
      <td><div class="tl">Total withdrawal</div><div class="tv dr mono">-{{ number_format($debits, 2) }}</div></td>
      <td><div class="tl">Closing balance</div><div class="tv mono">{{ number_format($closeBal, 2) }}</div></td>
    </tr>
  </table>

  <!-- Signatures -->
  <table class="layout sig-section">
    <tr>
      <td class="sig-col">
        <div class="sig-lbl">Customer relations officer</div>
        <div class="sig-name">{{ $officer->name ?? '—' }}</div>
        <div class="sig-role">{{ $officerRole }}</div>
        <div class="sig-line"><span class="sig-cursive">{{ $officerSignature ?? '—' }}</span></div>
        <div class="sig-date">Signature &middot; {{ now()->format('d M Y') }}</div>
      </td>
      <td class="seal-col">
        <div class="seal">
          <div class="seal-ring">
            <div class="seal-bank">NABAAD BANK</div>
            <div class="seal-branch">{{ strtoupper($branch->name ?? 'GAROWE BRANCH') }}</div>
            <div class="seal-word">CERTIFIED</div>
            <div class="seal-word2">STATEMENT</div>
            <div class="seal-date">{{ now()->format('d · m · Y') }}</div>
          </div>
        </div>
      </td>
      <td class="sig-col right">
        <div class="sig-lbl">Branch manager</div>
        <div class="sig-name">{{ $manager->name ?? '—' }}</div>
        <div class="sig-role">Branch Manager{{ $branch ? ' — ' . $branch->name : '' }}</div>
        <div class="sig-line"><span class="sig-cursive">{{ $managerSignature ?? '—' }}</span></div>
        <div class="sig-date">Signature &middot; {{ now()->format('d M Y') }}</div>
      </td>
    </tr>
  </table>

  <!-- Notice -->
  <div class="notice">
    This is an official account statement issued by NABAAD Bank. For queries or discrepancies, please contact your branch within 30 days of the statement date. All amounts are shown in {{ $account->currency ?? 'USD' }}. Valid only with official bank stamp and authorized signatures.
  </div>

  <!-- Footer -->
  <table class="layout doc-footer">
    <tr>
      <td class="fn mono">Ref: {{ $statementRef }} &middot; {{ $account->account_number }} &middot; Confidential</td>
      <td class="fb">NABAAD Bank &middot; {{ $branch->name ?? 'Garowe Branch' }} &middot; {{ $branch->phone ?? '+252 5 842000' }}</td>
    </tr>
  </table>

</body>
</html>
