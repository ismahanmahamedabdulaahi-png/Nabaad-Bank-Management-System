@component('mail::message')
Hello {{ $name }},

{{ $intro }}

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="receipt-panel">
<tr>
<td align="center">
<p class="receipt-label">{{ $badgeLabel }}</p>
<p class="receipt-amount is-credit" style="font-size:26px;">{{ $badgeValue }}</p>
</td>
</tr>
</table>

@if(count($points))
@foreach($points as $point)
✓&nbsp;&nbsp;{{ $point }}

@endforeach
@endif

@component('mail::button', ['url' => $url, 'color' => 'primary'])
{{ $buttonText }}
@endcomponent

This link expires in {{ $expiresIn }}.

If you weren't expecting this, you can safely ignore this email.

Regards,<br>
NABAAD Bank
@endcomponent
