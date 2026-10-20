@include('portal.partials.head', ['title' => '🛒 احسب سلتك'])
@guest('customer')
<div class="card" style="background:#262c4a;color:#fff">
    <b style="font-size:16px">احسب سعر سلتك في ثواني — من غير حساب 🎉</b>
    <div style="font-size:13px;opacity:.85;margin-top:4px">وكيف يعجبك السعر، ادخل أو اعمل حساب وأكد طلبك.</div>
    <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap"><a class="btn green" href="{{ route('customer.login') }}" style="padding:8px 14px">🔑 دخول</a><a class="btn ghost" href="{{ route('customer.register') }}" style="padding:8px 14px">📝 حساب جديد</a></div>
</div>
@endguest
<div class="card">
    <b>الصق رابط السلة</b>
    <div class="note" style="margin:4px 0 10px">SHEIN: افتح السلة ← مشاركة ← انسخ الرابط. ويصير زادة رابط iHerb أو منتج Amazon.</div>
    @if ($block)<div class="flash e">{{ $block }}</div>@else
    <form method="post" action="{{ route('portal.carts.store') }}">
        @csrf
        <textarea name="link" class="inp" rows="3" placeholder="https://…" required>{{ old('link') }}</textarea>
        <div class="note" style="margin:8px 0">لو منتج Amazon، كم قطعة؟ <input name="qty" type="number" min="1" max="99" value="1" class="inp" style="width:80px;display:inline-block;padding:6px 10px"></div>
        <button class="btn block">احسب السعر</button>
    </form>
    @endif
</div>
@if ($wa->count())
<div class="card" style="border:2px solid #1d8a5f">
    <b>💬 سلاتك من الواتساب</b><div class="note">حسبناهملك على الواتساب — اطلبها من هني وعدّل الكميات</div>
    @foreach ($wa as $w)
        <form method="post" action="{{ route('portal.cart.claim', $w->id) }}" style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #eef0f4">@csrf
            <span>{{ $w->retailer_label }} <span class="note num">#{{ $w->id }} · {{ $w->created_at->format('Y-m-d H:i') }}</span></span><button class="btn green" style="padding:8px 14px">اطلب الآن</button></form>
    @endforeach
</div>
@endif
@if ($carts->count())
@php
    $stOf = function ($ct) {
        if (in_array($ct->status, ['failed', 'expired', 'cancelled'])) return ['تعذر الحساب', 'bad', '⚠️'];
        if ($ct->status !== 'succeeded') return ['جاري الحساب', 'wait', '⏳'];
        return ['draft' => ['السعر جاهز · ما طلبتهاش', 'new', '🧾'], 'ordered' => ['تستنى تأكيد الحجز', 'pend', '🕒'], 'confirmed' => ['تم تأكيد الحجز', 'ok', '✓'], 'rejected' => ['مرفوضة', 'bad', '✕']][$ct->portal_status] ?? ['السعر جاهز', 'new', '🧾'];
    };
    $groups = $carts->groupBy(fn ($ct) => $ct->created_at->isToday() ? 'اليوم' : ($ct->created_at->isYesterday() ? 'أمس' : ($ct->created_at->gte(now()->subDays(7)) ? 'هذا الأسبوع' : 'أقدم')));
    $rl = ['shein' => 'SHEIN', 'iherb' => 'iHerb', 'amazon' => 'Amazon'];
@endphp
<style>
.hist h4 { margin: 14px 4px 8px; font-size: 13px; color: #6b7086; font-weight: 700; }
.crt { display: grid; grid-template-columns: 44px 1fr auto; gap: 12px; align-items: center; background: #fff; border-radius: 16px; padding: 12px 14px; margin-bottom: 8px; text-decoration: none; color: inherit; border: 1px solid #eceef3; }
.crt .ic { width: 44px; height: 44px; border-radius: 12px; background: #262c4a; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 800; letter-spacing: .5px; }
.crt .ic.iherb { background: #458500; } .crt .ic.amazon { background: #131921; color: #ff9900; }
.crt .t1 { font-weight: 700; font-size: 14.5px; }
.crt .t2 { font-size: 12px; color: #6b7086; margin-top: 2px; }
.crt .r { text-align: left; }
.crt .r b { font-size: 16px; color: #262c4a; }
.tag { display: inline-block; font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 99px; margin-top: 4px; }
.tag.ok { background: #e6f6ee; color: #0f6b4c; } .tag.pend { background: #fff6e0; color: #8a5a00; } .tag.new { background: #eef1fb; color: #2a3f8f; } .tag.wait { background: #f2f3f7; color: #6b7086; } .tag.bad { background: #fdecea; color: #b3261e; }
.sum { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 4px; }
.sum div { background: #fff; border-radius: 14px; padding: 10px; text-align: center; border: 1px solid #eceef3; }
.sum b { display: block; font-size: 18px; color: #262c4a; } .sum small { color: #6b7086; font-size: 11.5px; }
</style>
<div class="hist">
    <div class="sum">
        <div><b class="num">{{ $carts->count() }}</b><small>سلة حسبتها</small></div>
        <div><b class="num">{{ $carts->where('portal_status', 'ordered')->count() }}</b><small>تستنى تأكيد</small></div>
        <div><b class="num">{{ $carts->where('portal_status', 'confirmed')->count() }}</b><small>حجز متأكد</small></div>
    </div>
    @foreach ($groups as $label => $list)
        <h4>{{ $label }}</h4>
        @foreach ($list as $ct)
            @php [$stT, $stC, $stI] = $stOf($ct); @endphp
            <a class="crt" href="{{ route('portal.cart', $ct->id) }}">
                <span class="ic {{ $ct->retailer }}">{{ $rl[$ct->retailer] ?? $ct->retailer_label }}</span>
                <span><div class="t1">سلة #{{ $ct->id }}</div><div class="t2"><span class="num">{{ $ct->created_at->format('Y-m-d · H:i') }}</span>@if ($ct->x_qty) · <span class="num">{{ $ct->x_qty }}</span> قطعة @endif @if ($ct->pay_method) · {{ $ct->pay_method === 'cash_courier' ? 'كاش مندوب' : 'تحويل' }}@endif</div><span class="tag {{ $stC }}">{{ $stI }} {{ $stT }}</span></span>
                <span class="r">@if ($ct->x_total)<b class="num">{{ number_format((float) $ct->x_total) }}</b><div class="t2">د.ل</div>@else<span class="t2">←</span>@endif</span>
            </a>
        @endforeach
    @endforeach
</div>
@endif
</div></body></html>
