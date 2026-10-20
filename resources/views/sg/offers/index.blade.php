@extends('sg.layout')
@section('title', 'العروض والخصومات')
@section('bodyClass', 'soft')
@php use App\Models\Offer; use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
@section('content')
<div class="top"><div><h1>العروض والخصومات والمناسبات</h1><div class="sub">كل عرض يصل إشعاراً لكل العملاء في حساباتهم · والخصم يعمل بكوبون يُطبَّق على الإيصال</div></div><div class="acts"><button class="sg-burger">☰</button></div></div>
<div class="grid g3 mb">
    <div class="card kpi accent"><div class="lbl">عروض فعّالة الآن</div><div class="v num">{{ $live }}</div><div class="s">من {{ $offers->total() }} عرض</div></div>
    <div class="card kpi"><div class="lbl">خصومات الكوبونات المستخدمة</div><div class="v"><span class="num">{{ $f($used) }}</span> <small>د.ل</small></div></div>
    <div class="card kpi"><div class="lbl">العملاء الذين يصلهم الإشعار</div><div class="v num">{{ $customers }}</div></div>
</div>
<div class="side-lay" style="grid-template-columns:1fr 400px;align-items:start">
    <div>
        @forelse ($offers as $o)
            <div class="card mb" style="{{ $o->active ? '' : 'opacity:.6' }}">
                <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap">
                    <span style="font-size:30px">{{ Offer::KINDS[$o->kind][1] ?? '🎁' }}</span>
                    <div style="flex:1;min-width:220px">
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b style="font-size:16px">{{ $o->title }}</b><span class="chip n">{{ Offer::KINDS[$o->kind][0] ?? '' }}</span>
                            @if ($o->discountLabel())<span class="chip u">{{ $o->discountLabel() }}{{ $o->max_discount ? ' · حتى ' . $f($o->max_discount) : '' }}</span>@endif
                            <span class="chip {{ $o->active ? 'b' : 'd' }}">{{ $o->active ? 'فعّال' : 'موقوف' }}</span></div>
                        @if ($o->body)<p style="margin:6px 0;line-height:1.8">{{ $o->body }}</p>@endif
                        <div class="note">{{ $o->starts_at ? 'من ' . $o->starts_at->format('Y-m-d') : 'من الآن' }}{{ $o->ends_at ? ' حتى ' . $o->ends_at->format('Y-m-d') : '' }} · وصل لـ {{ $o->notified }} عميل
                            @if ($o->coupon) · الكوبون <b class="num">{{ $o->coupon_code }}</b> <button type="button" class="cpy" data-copy="{{ $o->coupon_code }}">⧉</button> · استُخدم {{ $o->coupon->used_count }} مرة@endif</div>
                    </div>
                    <form method="post" action="{{ route('sg.offers.toggle', $o) }}">@csrf<button class="btn ghost sm">{{ $o->active ? 'إيقاف' : 'تفعيل' }}</button></form>
                </div>
            </div>
        @empty
            <div class="card"><div class="empty"><span class="ic">🎁</span>لا توجد عروض بعد — أنشئ أول عرض</div></div>
        @endforelse
        @include('sg.partials.pager', ['p' => $offers])
        <div class="card mt">
            <h3>آخر الكوبونات</h3>
            <div class="scroll-x"><table class="t"><thead><tr><th>الكود</th><th>الخصم</th><th>لمن</th><th>المصدر</th><th class="n">استُخدم</th><th>ينتهي</th></tr></thead><tbody>
            @forelse ($coupons as $c)
                <tr><td class="num"><b>{{ $c->code }}</b></td><td>{{ $c->label() }}{{ $c->max_discount ? ' (حتى ' . $f($c->max_discount) . ')' : '' }}</td><td>{{ optional($c->customer)->name ?: 'كل العملاء' }}</td>
                    <td>{{ ['points' => '⭐ نقاط', 'offer' => '🎁 عرض', 'manual' => 'يدوي'][$c->source] ?? $c->source }}</td><td class="n num">{{ $c->used_count }}{{ $c->uses_limit ? ' / ' . $c->uses_limit : '' }}</td><td class="note">{{ optional($c->expires_at)->format('Y-m-d') ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="6"><div class="empty">لا توجد كوبونات</div></td></tr>
            @endforelse
            </tbody></table></div>
        </div>
    </div>
    <div>
        <form class="card sticky" method="post" action="{{ route('sg.offers.store') }}" id="ofF">
            @csrf
            <h3>عرض / خصم / مناسبة جديدة</h3>
            <div class="opts mb">@foreach (Offer::KINDS as $k => $v)<label><input type="radio" name="kind" value="{{ $k }}" @checked(old('kind', 'offer') === $k)><span>{{ $v[1] }} {{ $v[0] }}</span></label>@endforeach</div>
            <div class="f"><label class="l">العنوان</label><input name="title" class="inp" required value="{{ old('title') }}" placeholder="مثلاً: خصم الجمعة البيضاء"></div>
            <div class="f"><label class="l">التفاصيل</label><textarea name="body" class="inp" rows="3" style="height:auto">{{ old('body') }}</textarea></div>
            <label class="l">الخصم (اختياري)</label>
            <div class="grid g2 f"><select name="discount_type" class="inp" id="dType"><option value="">بدون خصم</option><option value="percent" @selected(old('discount_type') === 'percent')>نسبة %</option><option value="amount" @selected(old('discount_type') === 'amount')>مبلغ د.ل</option></select><input name="discount_value" class="inp numr" placeholder="القيمة" value="{{ old('discount_value') }}"></div>
            <div id="dBox">
                <div class="grid g2 f"><div><label class="l">أقصى خصم (د.ل)</label><input name="max_discount" class="inp numr" value="{{ old('max_discount') }}"></div><div><label class="l">أقل قيمة للإيصال</label><input name="min_order" class="inp numr" value="{{ old('min_order') }}"></div></div>
                <div class="grid g2 f"><div><label class="l">كود الكوبون (اختياري)</label><input name="coupon_code" class="inp" style="direction:ltr" placeholder="تلقائي" value="{{ old('coupon_code') }}"></div><div><label class="l">مرات لكل عميل</label><input name="uses_per_customer" class="inp numr" value="{{ old('uses_per_customer', 1) }}"></div></div>
            </div>
            <div class="grid g2 f"><div><label class="l">يبدأ</label><input type="date" name="starts_at" class="inp" value="{{ old('starts_at', now()->toDateString()) }}"></div><div><label class="l">ينتهي</label><input type="date" name="ends_at" class="inp" value="{{ old('ends_at') }}"></div></div>
            <label class="ck mb"><input type="checkbox" name="notify" value="1" checked> إرسال إشعار لكل العملاء في حساباتهم ({{ $customers }})</label>
            <button class="btn block lg">نشر</button>
        </form>
        <form class="card mt" method="post" action="{{ route('sg.coupons.store') }}">
            @csrf
            <h3>كوبون لعميل معيّن</h3>
            <div class="f"><select name="customer_id" class="inp"><option value="">كل العملاء</option>@foreach (\App\Models\Customer::orderBy('name')->get(['id', 'name', 'code']) as $cu)<option value="{{ $cu->id }}">{{ $cu->name }} · {{ $cu->code }}</option>@endforeach</select></div>
            <div class="grid g2 f"><select name="type" class="inp"><option value="percent">نسبة %</option><option value="amount">مبلغ د.ل</option></select><input name="value" class="inp numr" placeholder="القيمة" required></div>
            <div class="grid g2 f"><input name="max_discount" class="inp numr" placeholder="أقصى خصم"><input type="date" name="expires_at" class="inp"></div>
            <button class="btn ghost block">إنشاء كوبون</button>
        </form>
    </div>
</div>
@endsection
@push('script')
<script>
(function () { const t = document.getElementById('dType'), b = document.getElementById('dBox'); const u = () => b.hidden = !t.value; t.addEventListener('change', u); u(); })();
</script>
@endpush
