@component('mail::message')
Hello {{ $customerName }},

{{ $intro }}

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="receipt-panel">
<tr>
<td align="center">
<p class="receipt-label">{{ $verb }}</p>
<p class="receipt-amount {{ $isCredit ? 'is-credit' : 'is-debit' }}">{{ $isCredit ? '+' : '-' }}{{ $currency }} {{ $amount }}</p>
@if($statusLabel)
<span class="receipt-status status-{{ $statusVariant }}">{{ $statusLabel }}</span>
@endif
</td>
</tr>
</table>

@component('mail::table')
| | |
|:---|---:|
| Reference | {{ $reference }} |
| Account | {{ $account }} |
@if($balance !== null)
| New Balance | {{ $currency }} {{ $balance }} |
@endif
| Date | {{ $date }} |
@if($reason)
| Reason | {{ $reason }} |
@endif
@endcomponent

@component('mail::button', ['url' => $url, 'color' => $isCredit || $statusVariant === null ? 'primary' : 'error'])
View Account
@endcomponent

{{ $footerNote }}

Regards,<br>
NABAAD Bank
@endcomponent
