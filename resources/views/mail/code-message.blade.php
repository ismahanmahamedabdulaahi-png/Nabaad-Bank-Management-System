@component('mail::message')
Hello {{ $name }},

{{ $intro }}

<p class="code-box">{{ $code }}</p>
@if($expiryText)
<p class="code-expiry">{{ $expiryText }}</p>
@endif

@if($extraLines)
@foreach($extraLines as $line)
{{ $line }}

@endforeach
@endif

@if($url)
@component('mail::button', ['url' => $url, 'color' => 'primary'])
{{ $buttonText }}
@endcomponent
@endif

{{ $footerNote }}

Regards,<br>
NABAAD Bank
@endcomponent
