{{-- 🏙 قائمة المدن (اقتراحات لخانة المدينة) --}}
<datalist id="lyCities"><option value="بنغازي">@foreach (array_filter(\App\Services\CourierService::OTHER_CITIES, fn ($c) => preg_match('/\p{Arabic}/u', $c)) as $c)<option value="{{ $c }}">@endforeach</datalist>
