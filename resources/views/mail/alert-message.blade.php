@component('mail::message')
{{ $greeting ?? ('Hello ' . $name . ',') }}

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="alert-banner alert-{{ $level }}">
<tr>
<td>
<strong>{{ $title }}</strong>
{{ $message }}
</td>
</tr>
</table>

@if($rows)
@component('mail::table')
| | |
|:---|---:|
@foreach($rows as $row)
| {{ $row['label'] }} | {{ $row['value'] }} |
@endforeach
@endcomponent
@endif

@if($extraLines)
@foreach($extraLines as $line)
{{ $line }}

@endforeach
@endif

@if($url)
@component('mail::button', ['url' => $url, 'color' => $buttonColor ?? 'primary'])
{{ $buttonText }}
@endcomponent
@endif

{{ $footerNote }}

Regards,<br>
NABAAD Bank
@endcomponent
