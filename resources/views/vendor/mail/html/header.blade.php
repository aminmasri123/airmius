@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo" alt="Laravel Logo">
@else
{!! $slot !!}
    <img src="{{ asset('img/logo/Airmius-Logo-Light.png') }}" class="logo" alt="Airmius Logo" width="220" height="32">
@endif
</a>
</td>
</tr>
