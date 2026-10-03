@component('mail::message')
{{ $greeting ?? ('Hello ' . $name . ',') }}

{{ $intro }}

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="credentials-panel">
@foreach($rows as $row)
<tr>
<td class="credentials-row">
<p class="credentials-label">{{ $row['label'] }}</p>
<p class="credentials-value">{{ $row['value'] }}</p>
</td>
</tr>
@endforeach
</table>

@if($url)
@component('mail::button', ['url' => $url, 'color' => 'primary'])
{{ $buttonText }}
@endcomponent
@endif

@if($extraLines)
@foreach($extraLines as $line)
{{ $line }}

@endforeach
@endif

{{ $footerNote }}

Regards,<br>
NABAAD Bank
@endcomponent
