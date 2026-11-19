@props(['url'])
@php
    // The logo as on the app's navy band: the stamp mark, then "Contrata" medium and "Eu" extra-bold.
    $name = trim(strip_tags($slot));
    $parts = preg_match('/^(.+?)(Eu)$/', $name, $match) === 1 ? [$match[1], $match[2]] : [$name, ''];
@endphp
<tr>
<td class="header">
<table class="header-inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="header-cell">
<a href="{{ $url }}" class="brand">
<img src="{{ asset('mail-stamp.png') }}" width="24" height="24" alt="" class="brand-mark">
<span class="brand-name">{{ $parts[0] }}<strong>{{ $parts[1] }}</strong></span>
</a>
</td>
</tr>
</table>
</td>
</tr>
