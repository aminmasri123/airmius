<x-mail::message>
# {{ $greeting }}

@foreach (preg_split('/\R+/', (string) $body) ?: [] as $line)
@if (trim($line) !== '')
{{ trim($line) }}

@endif
@endforeach

<x-mail::button :url="$approveUrl" color="success">
Zustimmen
</x-mail::button>

<x-mail::button :url="$rejectUrl" color="error">
Ablehnen
</x-mail::button>

Falls die Buttons nicht funktionieren, koennen Sie die Anfrage hier pruefen:
[Anfrage anzeigen]({{ $reviewUrl }})

Regards,<br>
Airmius
</x-mail::message>
