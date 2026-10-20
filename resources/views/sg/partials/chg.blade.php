{{-- سهم التغيير: $cur, $prev, $invert (الزيادة سيئة) --}}
@php
    $p = \App\Services\Period::pct($cur, $prev);
    $inv = $invert ?? false;
    $good = $inv ? $p <= 0 : $p >= 0;
@endphp
@if (abs($p) < 0.05)
    <span class="chip n">— <span class="num">0%</span></span>
@else
    <span class="chip {{ $good ? 'u' : 'd' }}">{{ $p > 0 ? '▲' : '▼' }} <span class="num">{{ number_format(abs($p), 1) }}%</span></span>
@endif
