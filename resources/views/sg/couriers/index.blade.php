@extends('sg.layout')
@section('title', 'المندوبين')
@php
    use App\Services\TreasuryService as TS; use App\Models\Delivery; use App\Models\User;
    $f = fn ($n) => TS::fmt($n);
    $palette = ['#2a78d6', '#6250d6', '#0f8a5f', '#eb6834', '#262c4a', '#b5480f'];
    $held = $bal->sum('expected');
    $pendingAll = $bal->sum(fn ($b) => $b['pending']->count());
@endphp
@push('style')
<style>
.cgrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; margin-bottom: 14px; }
.cc { display: block; background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px; }
.cc.on { border: 2px solid var(--brand); }
.cc.off { opacity: .5; }
.cc .g { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-top: 10px; }
.cc .g div { background: var(--line-soft); border-radius: 9px; padding: 6px 8px; font-size: 12px; color: var(--ink-3); }
.cc .g b { display: block; font-size: 15px; color: var(--ink); }
.dv { border: 1px solid var(--line); border-radius: 14px; margin-bottom: 10px; background: #fff; }
.dv.problem { border: 2px solid var(--down); }
.dv.focus { box-shadow: 0 0 0 3px #f6d48b; }
.dv summary { list-style: none; cursor: pointer; padding: 12px 14px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.dv summary::-webkit-details-marker { display: none; }
.dv .body { padding: 0 14px 14px; border-top: 1px dashed var(--line); }
.dv .ev { font-size: 12.5px; color: var(--ink-3); padding: 3px 0; }
.req { display: flex; gap: 10px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--line-soft); flex-wrap: wrap; }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>المندوبين والتوصيل</h1><div class="sub">من لديه طلبيات، كم معه أموال، ونُقفل معه الحساب</div></div>
    <div class="acts">@can('courier-create')<button class="btn" data-modal="mNewC">+ مندوب / مكتب توصيل</button>@endcan<button class="sg-burger">☰</button></div>
</div>

<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">طلبيات مع المندوبين الآن</div><div class="v num">{{ $pendingAll }}</div><div class="s">يقبضوا {{ $f($bal->sum('pendingAmount')) }} د.ل</div></div>
    <div class="card kpi"><div class="lbl">أموال عند المندوبين</div><div class="v"><span class="num">{{ $f($held) }}</span> <small>د.ل</small></div><div class="s">لم تُقفل</div></div>
    <div class="card kpi green"><div class="lbl">وصلت اليوم</div><div class="v num">{{ $today }}</div></div>
    <div class="card kpi {{ $requests->count() + $problems->count() + $outside->count() ? 'red' : '' }}"><div class="lbl">تحتاج تتصرف</div><div class="v num">{{ $requests->count() + $problems->count() + $outside->count() }}</div><div class="s">{{ $requests->count() }} طلب توصيل · {{ $problems->count() }} مشكلة{{ $outside->count() ? ' · ' . $outside->count() . ' خارج بنغازي' : '' }}</div></div>
</div>

@if ($requests->count() || $problems->count())
<div class="grid g2 mb">
    <div class="card">
        <h3>طلبات توصيل من العملاء <span class="h-note">من حساباتهم</span></h3>
        @forelse ($requests as $r)
            <div class="req">
                <div style="flex:1;min-width:200px"><b>{{ optional($r->customer)->name }}</b> <span class="code note">{{ $r->code }}</span> <span class="chip w num">🕒 {{ optional($r->created_at)->format('m-d g:i a') }}</span><div class="note">{{ $r->orders->pluck('code')->implode('، ') }} · {{ $r->address }} · نقداً {{ $f($r->plan_cash) }} / مصرف {{ $f($r->plan_bank) }} @if ($r->bank_receipt)· <a href="{{ asset('storage/' . $r->bank_receipt) }}" target="_blank" style="color:var(--s1)">📎 إيصال التحويل</a>@endif</div></div>
                @can('courier-edit')
                <form method="post" action="{{ route('sg.couriers.delivery.update', $r) }}" style="display:flex;gap:6px">@csrf @method('PUT')<input type="hidden" name="notify" value="1">
                    <select name="courier_id" class="inp" style="height:36px;width:150px" required><option value="">اختر مندوب</option>@foreach ($couriers->where('status', true) as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select><select name="fee_payer" class="inp" style="height:36px;width:auto" title="أجرة التوصيل">@foreach (\App\Models\Delivery::FEE_PAYERS as $k => $v)<option value="{{ $k }}" @selected(($r->fee_payer ?: 'customer') === $k)>الأجرة {{ $v }}</option>@endforeach</select><button class="btn sm green">تعيين</button>
                </form>
                <form method="post" action="{{ route('sg.couriers.action', $r) }}" data-confirm="نرفضو طلب التوصيل؟ الإيصالات ترجع للرف">@csrf<input type="hidden" name="a" value="cancelled"><button class="btn red sm">رفض</button></form>
                @endcan
            </div>
        @empty
            <div class="note">لا يوجد</div>
        @endforelse
    </div>
    <div class="card">
        <h3>مشاكل في التوصيل</h3>
        @forelse ($problems as $r)
            <div class="req"><span class="chip d">⚠</span><div style="flex:1"><b>{{ $r->code }}</b> · {{ optional($r->courier)->name }}<div class="note">{{ $r->problem }} · {{ $r->recipient_name }} <span class="num">{{ $r->recipient_phone }}</span></div></div><a class="btn ghost sm" href="?d={{ $r->id }}">افتح</a></div>
        @empty
            <div class="note">لا يوجد ✓</div>
        @endforelse
    </div>
</div>
@endif


@if ($outside->count())
<div class="card mb" style="border:2px solid #f0c36d">
    <h3>🚚 طلبيات خارج بنغازي <span class="h-note">ما تمشيش للمندوب أوتوماتيك · اختار مندوب/مكتب شحن، ولا ارفضها</span></h3>
        @forelse ($outside as $r)
            <div class="req">
                <div style="flex:1;min-width:200px"><b>{{ optional($r->customer)->name }}</b> <span class="chip w">📍 {{ $r->city }}</span> <span class="code note">{{ $r->code }}</span> <span class="chip w num">🕒 {{ optional($r->created_at)->format('m-d g:i a') }}</span><div class="note">{{ $r->orders->pluck('code')->implode('، ') }} · {{ $r->address }} · نقداً {{ $f($r->plan_cash) }} / مصرف {{ $f($r->plan_bank) }} @if ($r->bank_receipt)· <a href="{{ asset('storage/' . $r->bank_receipt) }}" target="_blank" style="color:var(--s1)">📎 إيصال التحويل</a>@endif</div></div>
                @can('courier-edit')
                <form method="post" action="{{ route('sg.couriers.delivery.update', $r) }}" style="display:flex;gap:6px">@csrf @method('PUT')<input type="hidden" name="notify" value="1">
                    <select name="courier_id" class="inp" style="height:36px;width:150px" required><option value="">اختر مندوب</option>@foreach ($couriers->where('status', true) as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select><select name="fee_payer" class="inp" style="height:36px;width:auto" title="أجرة التوصيل">@foreach (\App\Models\Delivery::FEE_PAYERS as $k => $v)<option value="{{ $k }}" @selected(($r->fee_payer ?: 'customer') === $k)>الأجرة {{ $v }}</option>@endforeach</select><button class="btn sm green">تعيين</button>
                </form>
                <form method="post" action="{{ route('sg.couriers.action', $r) }}" data-confirm="نرفضو طلب التوصيل؟ الإيصالات ترجع للرف">@csrf<input type="hidden" name="a" value="cancelled"><button class="btn red sm">رفض</button></form>
                @endcan
            </div>
        @empty
            <div class="note">لا يوجد</div>
        @endforelse
</div>
@endif
<div class="cgrid">
    @forelse ($couriers as $c)
        @php $b = $bal[$c->id]; @endphp
        <a class="cc {{ $sel && $sel->id === $c->id ? 'on' : '' }} {{ $c->status ? '' : 'off' }}" href="?id={{ $c->id }}">
            <div class="who"><span class="av" style="background:{{ $palette[$c->id % 6] }}">{{ mb_substr($c->name, 0, 1) }}</span><div><b>{{ $c->isOffice() ? '🏢 ' : '' }}{{ $c->name }}</b><div class="note"><span class="num">{{ $c->phone }}</span>{{ $c->courier_zone ? ' · ' . $c->courier_zone : '' }}{{ $c->status ? '' : ' · موقوف' }}@php $rt = \Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'rating') ? \App\Models\Delivery::where('courier_id', $c->id)->whereNotNull('rating')->selectRaw('AVG(rating) a, COUNT(*) n')->first() : null; @endphp @if ($rt && $rt->n) · <span style="color:#c99a06">★ {{ number_format($rt->a, 1) }}</span> <small>({{ $rt->n }})</small>@endif</div></div></div>
            <div class="g"><div>معه<b class="num">{{ $b['pending']->count() }}</b></div><div>وصّل<b class="num">{{ $b['count'] }}</b></div><div>{{ $b['expected'] < 0 ? 'علينا له' : 'يجب أن يسلّم' }}<b class="num {{ $b['expected'] > 0 ? 'minus' : 'plus' }}">{{ $f(abs($b['expected'])) }}</b></div></div>
        </a>
    @empty
        <div class="card"><div class="empty"><span class="ic">🚚</span>لا يوجد مندوبين لا يزال — أضف أول مندوب</div></div>
    @endforelse
</div>

@if ($sel)
@php $b = $bal[$sel->id]; @endphp
<div class="side-lay" style="grid-template-columns:1fr 380px">
    <div>
        <div class="card tw mb">
            <div class="tw-h"><h3>مع {{ $sel->name }} الآن <span class="h-note">{{ $detail['pending']->count() }} توصيل · يقبض {{ $f($b['pendingAmount']) }} د.ل</span></h3><button class="btn ghost sm" data-modal="mEditC" style="margin-inline-start:auto">⚙ بيانات وصلاحيات المندوب</button></div>
            <div style="padding:12px">
            @forelse ($detail['pending'] as $d)
                @include('sg.couriers._delivery', ['d' => $d])
            @empty
                <div class="empty" style="padding:24px">ليس لديه طلبيات الآن</div>
            @endforelse
            </div>
        </div>
        <div class="card tw">
            <div class="tw-h"><h3>آخر التوصيلات</h3></div>
            <div class="scroll-x">
            <table class="t"><thead><tr><th>التوصيل</th><th>العميل</th><th>الحالة</th><th class="n">نقداً</th><th class="n">مصرف</th><th class="n">أجرته</th><th>الحساب</th></tr></thead><tbody>
            @forelse ($detail['recent'] as $d)
                <tr><td><a class="code" href="?d={{ $d->id }}">{{ $d->code }}</a> <span class="note">{{ optional($d->delivered_at ?? $d->updated_at)->format('j/n g:i') }}</span></td><td class="cell2"><b>{{ $d->recipient_name }}</b>@if (!$d->customer_id && $d->cart_id) <span class="chip d" style="background:#e03131;color:#fff;font-size:11px">⚠ غير مسجل · بدون إيصال</span>@endif<small class="num">{{ $d->orders->pluck('code')->implode(' ') }}</small></td><td><span class="chip {{ $d->statusChip() }}">{{ $d->statusLabel() }}</span>@if (!empty($d->proof_path)) <a href="{{ asset('storage/' . $d->proof_path) }}" target="_blank" title="صورة التسليم">📸</a>@endif</td><td class="n num">{{ $f($d->collected_cash) }}</td><td class="n num">{{ $f($d->collected_bank) }}</td><td class="n num">{{ $f($d->fee) }}</td><td>@if ($d->status === 'delivered'){!! $d->settlement_id ? '<span class="chip u">أُقفل</span>' : '<span class="chip w">معه</span>' !!}@endif</td></tr>
            @empty
                <tr><td colspan="7"><div class="empty">لا يوجد</div></td></tr>
            @endforelse
            </tbody></table>
            </div>
        </div>
    </div>
    <div>
        <div class="card dark mb sticky">
            <h3>إقفال الحساب مع {{ $sel->name }}</h3>
            <div class="ln"><span>وصّل (لم يُقفل)</span><b class="num">{{ $b['count'] }} توصيل</b></div>
            <div class="ln"><span>قبض نقداً من العملاء</span><b class="num">{{ $f($b['cash']) }}</b></div>
            <div class="ln"><span>− أجرته (التوصيل)</span><b class="num" style="color:#ff8a80">−{{ $f($b['fees']) }}</b></div>
            @if (abs($b['carry']) >= 0.01)<div class="ln"><span>{{ $b['carry'] > 0 ? '+ باقي عليه من المرة السابقة' : '− زيادة له من المرة السابقة' }}</span><b class="num">{{ $f($b['carry']) }}</b></div>@endif
            <div class="ln"><span>المصرف (دخل الخزينة مباشرة)</span><b class="num">{{ $f($b['bank']) }}</b></div>
            <div class="ln tot"><span style="color:#fff">يجب أن يسلّم نقداً</span><b><span class="num" id="sExp" data-v="{{ $b['expected'] }}">{{ $f($b['expected']) }}</span> د.ل</b></div>
            <div class="ln"><span>لا يزال معه (لم يصل)</span><b class="num">{{ $b['pending']->count() }} طلبية</b></div>
            @php $cr = $sel->close_request ? json_decode($sel->close_request, true) : null; @endphp
            @if ($cr)
                <div style="background:#fff4dc;color:#7a4e00;border-radius:12px;padding:10px 12px;margin-top:12px;font-size:13.5px">📨 طلب المندوب إقفال الحساب: سلّم <b class="num">{{ $f($cr['amount'] ?? 0) }}</b> د.ل{{ !empty($cr['to']) ? ' إلى ' . $cr['to'] : '' }} · {{ \Carbon\Carbon::parse($cr['at'] ?? now())->diffForHumans() }}{{ !empty($cr['note']) ? ' · ' . $cr['note'] : '' }}</div>
            @endif
            @can('courier-settle')
            <form method="post" action="{{ route('sg.couriers.settle', $sel) }}" data-confirm="إقفال الحساب؟" style="background:rgba(255,255,255,.08);border-radius:12px;padding:12px;margin-top:12px">
                @csrf
                <label class="l" style="color:#c9cde0">كم سلّم فعلياً؟</label>
                <input name="received" id="sRec" class="inp numr" value="{{ $cr ? $cr['amount'] : floor($b['expected']) }}" required>
                <div class="grid g2" style="margin-top:8px">
                    <div><label class="l" style="color:#c9cde0">كسور تتلغى</label><input name="rounding" id="sRnd" class="inp numr" value="{{ round($b['expected'] - floor($b['expected']), 2) }}"></div>
                    <div><label class="l" style="color:#c9cde0">في خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }}</option>@endforeach</select></div>
                </div>
                <div id="sDiff" style="font-size:13.5px;margin-top:8px;color:#c9cde0"></div>
                <input name="note" class="inp" style="height:38px;margin-top:8px" placeholder="ملاحظة (اختياري)">
                <button class="btn green block mt">إقفال الحساب</button>
            </form>
            @endcan
            @if ($detail['settlements']->count())
                <div style="margin-top:14px;font-weight:700;font-size:14px">آخر التسكيرات</div>
                @foreach ($detail['settlements'] as $s)
                    <div class="ln" style="font-size:13px"><span>{{ $s->created_at->format('j/n g:i') }} · {{ $s->deliveries_count }} توصيل</span><span><b class="num">{{ $f($s->received) }}</b>@if ($s->rounding > 0) <span style="color:#c9cde0">(كسور {{ $f($s->rounding) }})</span>@endif @if (abs($s->diff) >= 0.01)<span class="chip d num">{{ $f($s->diff) }}</span>@endif</span></div>
                @endforeach
            @endif
        </div>
    </div>
</div>

@can('courier-edit')
<div class="modal-bg" id="mEditC"><div class="modal">
    <h3>{{ $sel->name }}</h3>
    <form method="post" action="{{ route('sg.couriers.update', $sel) }}">
        @csrf @method('PUT')
        <div class="grid g2 f"><div><label class="l">الاسم (يدخل به)</label><input name="name" class="inp" value="{{ $sel->name }}" required></div><div><label class="l">الهاتف</label><input name="phone" class="inp numr" value="{{ $sel->phone }}" required></div></div>
        <div class="grid g2 f"><div><label class="l">المنطقة</label><input name="courier_zone" class="inp" value="{{ $sel->courier_zone }}"></div><div><label class="l">كلمة سر جديدة</label><input name="password" class="inp" placeholder="اتركه فارغاً لعدم التغيير"></div></div>
        <label class="ck"><input type="checkbox" name="courier_hide_phone" value="1" @checked($sel->courier_hide_phone ?? false)> 🙈 خبّي رقمه على العملاء (يطلعلهم رقم المحل بداله)</label>
        <label class="l">الصلاحيات</label>
        @foreach (User::COURIER_PERMS as $k => $v)<label class="tgl" style="display:flex;margin-bottom:8px"><input type="checkbox" name="perms[{{ $k }}]" value="1" @checked($sel->courierCan($k))><span class="tg"></span>{{ $v }}</label>@endforeach
        <label class="tgl" style="display:flex;margin:12px 0"><input type="checkbox" name="status" value="1" @checked($sel->status)><span class="tg"></span>الحساب مفعل</label>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endcan
@elseif ($focus)
    <div class="card">@include('sg.couriers._delivery', ['d' => $focus])</div>
@endif

@can('courier-create')
<div class="modal-bg" id="mNewC"><div class="modal">
    <h3>مندوب / مكتب توصيل جديد</h3>
    <form method="post" action="{{ route('sg.couriers.store') }}" id="newCF">
        @csrf
        <div class="opts mb"><label><input type="radio" name="courier_kind" value="person" checked><span>🛵 مندوب (يدخل للنظام)</span></label><label><input type="radio" name="courier_kind" value="office"><span>🏢 مكتب توصيل خارجي</span></label></div>
        <div class="hint mb" id="offHint" hidden>المكتب لا يدخل للنظام. تسند له التوصيلات: إذا قبض من العميل يتجمع المبلغ عنده، وأجرته تُحسب علينا. وعند إقفال الحساب يظهر الفرق: لنا عليه أو علينا له.</div>
        <div class="grid g2 f"><div><label class="l">الاسم (يدخل به)</label><input name="name" class="inp" required></div><div><label class="l">الهاتف</label><input name="phone" class="inp numr" required></div></div>
        <div class="grid g2 f"><div><label class="l">كلمة المرور</label><input name="password" class="inp" required minlength="8" id="ncPw"></div><div><label class="l">المنطقة</label><input name="courier_zone" class="inp" placeholder="طرابلس، تاجوراء…"></div></div>
        <label class="l">الصلاحيات</label>
        @foreach (User::COURIER_PERMS as $k => $v)<label class="tgl" style="display:flex;margin-bottom:8px"><input type="checkbox" name="perms[{{ $k }}]" value="1" @checked($k !== 'edit_recipient')><span class="tg"></span>{{ $v }}</label>@endforeach
        <div class="hint mb">المندوب يدخل من صفحة دخول الموظفين باسمه وكلمة السر، وتفتحله واجهة المندوب فقط.</div>
        <div style="display:flex;gap:8px"><button class="btn">إضافة</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
        <script>
        (function () { const f = document.getElementById('newCF'); if (!f) return;
          const u = () => { const off = f.querySelector('input[name=courier_kind]:checked').value === 'office'; document.getElementById('offHint').hidden = !off; const pw = document.getElementById('ncPw'); pw.required = !off; pw.closest('div').style.display = off ? 'none' : ''; };
          f.querySelectorAll('input[name=courier_kind]').forEach(i => i.addEventListener('change', u)); u(); })();
        </script>
    </form>
</div></div>
@endcan
@endsection
@push('script')
<script>
(function () {
  const r = document.getElementById('sRec'), n = document.getElementById('sRnd'), e = document.getElementById('sExp'), d = document.getElementById('sDiff');
  if (!r) return;
  const upd = () => { const v = parseFloat(e.dataset.v) - (parseFloat(r.value) || 0) - (parseFloat(n.value) || 0); d.innerHTML = Math.abs(v) < 0.01 ? '<b style="color:#5fd3a3">✓ الحساب مظبوط</b>' : (v > 0 ? 'يبقى عليه <b class="num" style="color:#ff9b94">' + SG.fmt2(v) + '</b> د.ل للمرة القادمة' : 'زيادة له <b class="num">' + SG.fmt2(-v) + '</b> د.ل'); };
  r.addEventListener('input', () => { const v = parseFloat(e.dataset.v) - (parseFloat(r.value) || 0); n.value = v > 0 && v < 5 ? Math.round(v * 100) / 100 : 0; upd(); });
  n.addEventListener('input', upd); upd();
})();
</script>
@endpush
