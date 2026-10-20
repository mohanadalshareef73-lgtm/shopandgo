{{-- أزرار الفترة: اليوم / الأسبوع / الشهر / السنة --}}
<div class="seg">
    @foreach (\App\Services\Period::PRESETS as $k => $v)
        <a href="{{ request()->fullUrlWithQuery(['p' => $k, 'from' => null, 'to' => null, 'page' => null]) }}" class="{{ $period->preset === $k ? 'on' : '' }}">{{ $v }}</a>
    @endforeach
</div>
