@php $f = fn ($n) => \App\Services\TreasuryService::fmt($n); $isFocus = isset($focus) && $focus && $focus->id === $d->id; @endphp
<details class="dv {{ $d->status === 'problem' ? 'problem' : '' }} {{ $isFocus ? 'focus' : '' }}" @if ($isFocus || $d->status === 'problem') open @endif>
    <summary>
        <span class="code">{{ $d->code }}</span>
        <span class="chip {{ $d->statusChip() }}">{{ $d->statusLabel() }}</span>
        <div style="flex:1;min-width:180px"><b>{{ $d->recipient_name }}</b> <span class="num note">{{ $d->recipient_phone }}</span><div class="note">{{ $d->address ?: 'بدون عنوان' }} · {{ $d->parcels }} طرد · {{ $d->orders->pluck('code')->implode('، ') }}</div></div>
        <div style="text-align:left"><b class="num" style="font-size:17px">{{ $f($d->toCollect()) }}</b> <span class="note">د.ل</span><div class="note">{{ \App\Models\Delivery::PAY_TYPES[$d->pay_type] ?? '' }} · أجرة {{ $f($d->fee) }}{{ $d->companyPays() ? ' (على الشركة)' : '' }}</div></div>
    </summary>
    <div class="body">
        @if ($d->problem)<div class="flash err" style="margin:12px 0 0">⚠ {{ $d->problem }}</div>@endif
        @can('courier-edit')
        <form method="post" action="{{ route('sg.couriers.delivery.update', $d) }}" class="grid g3" style="margin-top:12px">
            @csrf @method('PUT')
            <div><label class="l">اسم المستلم</label><input name="recipient_name" class="inp" value="{{ $d->recipient_name }}"></div>
            <div><label class="l">رقم المستلم</label><input name="recipient_phone" class="inp numr" value="{{ $d->recipient_phone }}"></div>
            <div><label class="l">رقم آخر</label><input name="recipient_phone2" class="inp numr" value="{{ $d->recipient_phone2 }}"></div>
            <div><label class="l">المدينة</label><input name="city" class="inp" value="{{ $d->city }}"></div>
            <div style="grid-column:span 2"><label class="l">العنوان</label><input name="address" class="inp" value="{{ $d->address }}"></div>
            <div style="grid-column:span 2"><label class="l">الموقع (رابط Google Maps)</label><input name="location_url" class="inp" style="direction:ltr" value="{{ $d->location_url }}"></div>
            <div><label class="l">المندوب</label><select name="courier_id" class="inp">@foreach (\App\Services\CourierService::couriers()->where('status', true) as $c)<option value="{{ $c->id }}" @selected($d->courier_id == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div><label class="l">أجرة التوصيل</label><div style="display:flex;gap:6px"><input name="fee" class="inp numr" value="{{ $d->fee }}" style="flex:1;min-width:0"><select name="fee_payer" class="inp" style="width:auto">@foreach (\App\Models\Delivery::FEE_PAYERS as $k => $v)<option value="{{ $k }}" @selected(($d->fee_payer ?: 'customer') === $k)>{{ $v }}</option>@endforeach</select></div></div>
            <div><label class="l">الدفع</label><select name="pay_type" class="inp">@foreach (\App\Models\Delivery::PAY_TYPES as $k => $v)<option value="{{ $k }}" @selected($d->pay_type === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label class="l">ملاحظات التوصيل</label><input name="note" class="inp" value="{{ $d->note }}"></div>
            <div style="grid-column:span 3;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <button class="btn sm">حفظ التعديل</button>
                @if ($d->mapUrl())<a class="btn ghost sm" target="_blank" href="{{ $d->mapUrl() }}">📍 الخريطة</a>@endif
                <a class="btn ghost sm" target="_blank" href="{{ route('sg.couriers.label', $d) }}">🖨 ورقة المندوب</a>
                @if ($d->bank_receipt)<a class="btn ghost sm" target="_blank" href="{{ asset('storage/' . $d->bank_receipt) }}">📎 إيصال التحويل</a>@endif
            </div>
        </form>
        @if ($d->isOpen())
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px dashed var(--line)">
            <form method="post" action="{{ route('sg.couriers.action', $d) }}" style="display:flex;gap:6px;align-items:center">@csrf<input type="hidden" name="a" value="delivered"><input name="cash" class="inp numr" style="height:34px;width:90px" value="{{ $d->pay_type === 'bank' ? 0 : round($d->toCollect(), 2) }}" title="نقداً"><input name="bank" class="inp numr" style="height:34px;width:80px" value="{{ $d->pay_type === 'bank' ? round($d->amount, 2) : 0 }}" title="مصرف"><button class="btn green sm">✓ تم التسليم (بالنيابة)</button></form>
            <form method="post" action="{{ route('sg.couriers.action', $d) }}" data-confirm="الطلبية عادت للمحل؟ الإيصالات ترجع للرف">@csrf<input type="hidden" name="a" value="returned"><button class="btn ghost sm">↩ عادت للمحل</button></form>
            <form method="post" action="{{ route('sg.couriers.action', $d) }}" data-confirm="إلغاء التوصيل؟">@csrf<input type="hidden" name="a" value="cancelled"><button class="btn red sm">إلغاء</button></form>
        </div>
        @endif
        @endcan
        <div style="margin-top:10px">@foreach ($d->events as $e)<div class="ev">{{ $e->created_at->format('j/n g:i A') }} · {{ optional($e->user)->name }} · {{ \App\Models\Delivery::STATUSES[$e->status][0] ?? $e->status }}{{ $e->note ? ' — ' . $e->note : '' }}</div>@endforeach</div>
    </div>
</details>
