@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo" alt="Laravel Logo">
@else
{!! $slot !!}
    <img src="{{ asset('img/logo/Logo-Airmius-mit-Schrift.png') }}" class="logo" alt="Airmius Logo" width="192" height="192">
@endif
</a>
</td>
</tr>
