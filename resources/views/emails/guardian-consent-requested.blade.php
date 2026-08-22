<x-mail::message>
# {{ $greeting }}

@foreach (preg_split('/\R+/', (string) $body) ?: [] as $line)
@if (trim($line) !== '')
{{ trim($line) }}

@endif
@endforeach

Einwilligungsversion: **{{ $consentVersion }}**

<x-mail::button :url="$reviewUrl" color="primary">
Anfrage prüfen
</x-mail::button>

Bitte prüfen Sie Kind, Einwilligungstext und Version auf der Airmius-Seite. Erst dort können Sie
zustimmen oder ablehnen.

Viele Grüße,<br>
Airmius
</x-mail::message>
