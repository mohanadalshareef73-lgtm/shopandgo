@extends('sg.layout')
@section('title', 'طلبيات دليفري')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); $ph = fn ($p) => (str_contains((string) $p, '@') || strlen(preg_replace('/\D/', '', (string) $p)) > 13) ? '' : $p; @endphp
@push('style')
<style>
.dtabs { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.dtabs a { padding: 9px 16px; border-radius: 12px; background: #fff; border: 1px solid var(--line); font-weight: 700; }
.dtabs a.on { background: var(--brand); color: #fff; border-color: var(--brand); }
table.t td.w { white-space: normal; min-width: 110px; }
.dp table.t td, .dp table.t th { padding: 8px 8px; font-size: 13px; }
.dp .scroll-x { overflow-x: auto; max-width: 100%; }
.dp { min-width: 0; max-width: 100%; }
.abar { position: sticky; bottom: 0; z-index: 20; background: #fff; border-top: 2px solid var(--brand); padding: 12px 14px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.abar .inp { height: 38px; width: auto; }
.kd { font-size: 12px; padding: 2px 8px; border-radius: 8px; border: 0; cursor: pointer; font: inherit; }
.kd.order { background: #e7f0ff; color: #1c45a3; } .kd.deposit { background: #fff1e6; color: #b5480f; }
.qa { display: flex; gap: 4px; flex-wrap: wrap; align-items: center; min-width: 150px; }
/* 📏 صغير زي واجهة الطلبات */
.dp table.t td, .dp table.t th { padding: 6px 8px !important; font-size: 13px !important; line-height: 1.45; vertical-align: middle; }
.dp table.t td b, .dp table.t td .num, .dp table.t td span { font-size: 13px !important; }
.dp table.t td small, .dp table.t td small * { font-size: 11.5px !important; }
.dp table.t td .btn, .dp .kd { padding: 3px 9px !important; font-size: 12px !important; min-height: 0; height: auto; border-radius: 8px; }
.dp table.t td .inp { height: 28px !important; font-size: 12.5px !important; padding: 0 6px; }
.dp table.t td .chip { padding: 1px 7px; }
.dp table.t tr td { border-radius: 0 !important; }
.dp .top h1 { font-size: 22px; } .dp .dtabs a { padding: 6px 12px; font-size: 13.5px; }
.qbar { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; padding: 10px 14px; background: var(--line-soft); border-bottom: 1px solid var(--line); }
.qbar .inp { height: 34px; width: auto; } .qbar .sep { flex: 1; }
</style>
@endpush
@section('content')
<div class="dp">
<div class="top">
    <div><h1>🛵 طلبيات دليفري</h1><div class="sub">طلبات العملاء من الرابط تنزل هني وتمشي لقروب الدليفري ← ✅ تم التسليم (طلبية) · ✓ تأكيد (عربون)</div></div>
    <div class="acts"><button class="sg-burger">☰</button></div>
</div>

<div class="dtabs">
    <a href="?tab=in" class="{{ $tab === 'in' ? 'on' : '' }}">🛵 بنغازي ({{ (int) ($counts['in'] ?? 0) }})</a>
    <a href="?tab=out" class="{{ $tab === 'out' ? 'on' : '' }}">🚚 خارج بنغازي ({{ (int) ($counts['out'] ?? 0) }})</a>
</div>

@if ($recent->count())
<details class="card mb" style="padding:10px 14px" @if (request('undo')) open @endif>
    <summary style="cursor:pointer;font-weight:800">🕘 آخر اللي صار (آخر 36 ساعة) — لو درت غلطة رجّعها من هني <span class="note">({{ $recent->count() }})</span></summary>
    <table class="t" style="margin-top:8px"><thead><tr><th>الوقت</th><th>الزبون</th><th>الإيصالات</th><th>شن صار</th><th></th></tr></thead><tbody>
    @foreach ($recent as $r)
        <tr>
            <td class="num">{{ $r->updated_at->format('m-d g:i a') }}</td>
            <td><b class="num">{{ optional($r->customer)->code }}</b> {{ optional($r->customer)->name ?: $r->recipient_name }}</td>
            <td class="num">{{ $r->cart_id ? 'سلة #' . $r->cart_id : ($r->orders->pluck('code')->implode('، ') ?: '—') }}</td>
            <td>{{ ['delivered' => '✅ مستلمة / مقبوضة', 'returned' => '↩ رجعت للرف', 'cancelled' => '✕ ملغية'][$r->status] ?? $r->status }}{{ (float) $r->collected_cash > 0 ? ' · ' . $f($r->collected_cash) . ' د.ل' : '' }}</td>
            <td><button type="button" class="btn sm" style="background:#262c4a;color:#fff" onclick="ra({{ $r->id }}, 'restore', 'نرجعوها للطابور زي ما كانت؟{{ $r->status === 'delivered' ? ($r->cart_id ? ' (الدفعة ترجع والإيصال اللي انعمل يتلغى والحجز يرجع يستنى تأكيد)' : ' (الدفعة اللي تسجلت وقتها ترجع)') : '' }}')">↩ رجّعها</button></td>
        </tr>
    @endforeach
    </tbody></table>
</details>
@endif

{{-- الطابور: منو مستلمها · علّم ← كشف / ترحيل / تعيين / رجوع --}}
<form method="post" action="{{ route('sg.dispatch.bulk') }}" id="bk" class="card tw mb">
    @csrf <input type="hidden" name="a" id="bkA">
    <div class="tw-h"><h3>{{ $tab === 'out' ? '🚚 خارج بنغازي' : '🛵 قائمة الدليفري' }} <span class="h-note">{{ $queue->count() }} طلب · {{ $f($queue->sum('amount')) }} د.ل</span></h3>
        @php $dGrp = \App\Services\DeliveryGroup::group(); @endphp
        <span class="note" style="margin-inline-start:auto">{!! $dGrp ? '📤 قروب الدليفري: <b>' . e($dGrp['name'] ?? $dGrp['id']) . '</b>' : '<span style="color:var(--down)">⚠ قروب الدليفري مش مسجل — من الإعدادات</span>' !!}</span>
    </div>
    @if ($tab === 'out')
    <div class="qbar" style="background:#fff4e5">
        <input type="search" class="inp" style="width:220px" placeholder="🔍 كود شركة التوصيل / كود العميل" value="{{ $xq }}" onkeydown="if(event.key==='Enter'){event.preventDefault();location='?tab=out&xq='+encodeURIComponent(this.value)}">
        @if ($xq !== '')<a class="btn ghost sm" href="?tab=out">✕ مسح</a>@endif
    </div>
    @endif
    @if ($queue->count())
    <div class="qbar">
        <b>المختار: <span id="nq" class="num">0</span></b>
        <button type="button" class="btn sm" onclick="pdfSel()">📄 كشف PDF للمختار</button>
        <button type="button" class="btn ghost sm" onclick="bk('{{ $tab === 'out' ? 'move_in' : 'move_out' }}')">{{ $tab === 'out' ? '🛵 ترحيل لبنغازي' : '🚚 ترحيل خارج بنغازي' }}</button>
        <span class="sep"></span>
        <button type="button" class="btn ghost sm" style="color:var(--down)" onclick="if(confirm('نرجعو المختارين للمحل والرف؟'))bk('back')">↩ للرف</button>
    </div>
    @endif
    <div class="scroll-x"><table class="t"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.qk').forEach(x=>x.checked=this.checked);cq()"></th><th>إجراء</th><th>الدفع</th><th>الزبون</th><th>العنوان</th><th>الإيصالات · التخزين</th><th class="n">الباقي</th></tr></thead><tbody>
    @forelse ($queue as $d)
            <tr>
            <td><input type="checkbox" class="qk" name="ids[]" value="{{ $d->id }}" onchange="cq()"></td>
            <td><div class="qa">
                @php $ct = $d->cart_id ? ($carts[$d->cart_id] ?? null) : null; $done = $d->status === 'delivered'; @endphp
                @if ($done)
                    {{-- ✓ متأكد: احجز الان · نسخ · PDF التكرار · ✓ إخفاء --}}
                    <span class="chip u">✓ متأكد</span>
                    @if ($ct)
                        @if ($bookTo !== '')<button type="button" class="btn sm" style="background:#e8590c;color:#fff" onclick="postTo('{{ route('sg.pcarts.booknow', $ct) }}', {to_phone: '{{ $bookTo }}'}, 'نبعتو الحجز #{{ $ct->id }} على الواتساب؟')">⚡ احجز الان{{ !empty($ct->book_sent_to) ? ' (مرة ثانية)' : '' }}</button>@else<a class="btn sm" style="background:#e8590c;color:#fff" href="{{ route('sg.pcarts', ['q' => $ct->id]) }}">⚡ احجز الان</a>@endif
                        @if (!empty($ct->book_sent_to))<small style="color:#1e7b45">✅ انبعت</small>@endif
                        <button type="button" class="btn ghost sm" data-copy="{{ $ct->source_url }}" onclick="cp(this)">⧉ نسخ الرابط</button>
                        @if (!empty($reps[$ct->id]))<a class="btn ghost sm" href="{{ route('sg.pcarts.download', $ct) }}">⬇ تحميل PDF</a><a class="btn ghost sm" style="color:#e8590c" href="{{ route('sg.pcarts.booking', $ct) }}" target="_blank">📄 PDF التكرار 🔁</a>@endif
                        @if (!empty($ct->order_id) && isset($cartOrders[$ct->order_id]))<a class="btn ghost sm" target="_blank" href="{{ route('order.invoice', $cartOrders[$ct->order_id]) }}">🖨 إيصال</a>@endif
                    @endif
                    @if ($d->customer)<button type="button" class="btn ghost sm" data-copy="{{ $d->customer->code }}" onclick="cp(this)">⧉ كود العميل {{ $d->customer->code }}</button>@endif
                    <button type="button" class="btn green sm" title="إخفاء ← حجوزات متأكدة" onclick="ra({{ $d->id }}, 'hide')">✓</button>
                @else
                @php $isDep = $d->kind === 'deposit' || $d->cart_id || $d->deposit_order_id;
                    $dj = ['id' => $d->id, 'code' => $d->code, 'name' => $d->recipient_name ?: optional($d->customer)->name, 'ccode' => optional($d->customer)->code, 'phone' => $ph($d->recipient_phone), 'city' => $d->city, 'area' => $d->area ?? null, 'address' => $d->address, 'note' => $d->note, 'amount' => round((float) $d->amount, 2), 'kind' => $isDep ? 'deposit' : 'order', 'pay_type' => $d->pay_type, 'cart' => $d->cart_id, 'what' => $d->cart_id ? 'سلة #' . $d->cart_id : $d->orders->pluck('code')->implode('، ')]; @endphp
                @if ($isDep)<button type="button" class="btn sm" style="background:#0f8a5f;color:#fff" onclick='openDl("confirm", @json($dj))'>✓ تأكيد</button>
                @else<button type="button" class="btn green sm" onclick='openDl("deliver", @json($dj))'>✅ تم التسليم</button>@endif
                <button type="button" class="btn ghost sm" onclick='openEd(@json($dj))'>✏ تعديل</button>
                <button type="button" class="btn ghost sm" onclick="const t=prompt('📝 الملاحظة اللي تبي تبعتها للعميل على الواتساب:');if(t&&t.trim())postTo(RA+{{ $d->id }},{a:'note',text:t})">📝 ملاحظة</button>
                <button type="button" class="btn ghost sm" title="{{ !empty($d->group_sent_at) ? 'انبعت للقروب ' . \Carbon\Carbon::parse($d->group_sent_at)->format('m-d g:i a') : 'ما انبعتش للقروب' }}" onclick="postTo(RA+{{ $d->id }},{a:'resend'},'نبعتوه لقروب الدليفري{{ !empty($d->group_sent_at) ? ' مرة ثانية' : '' }}؟')">📤{{ !empty($d->group_sent_at) ? '✓' : '' }}</button>
                @if ($tab === 'out')
                <input class="inp" style="width:120px;height:28px;font-size:12.5px" placeholder="كود الشركة" value="{{ $d->ext_code }}" data-id="{{ $d->id }}" onchange="ext(this)">
                @endif
                @if (!$isDep)<button type="button" class="btn ghost sm" onclick="ra({{ $d->id }}, 'done', 'نفتحو صفحة التسليم باش تدير الدفع بنفسك؟')">💵 بالكاشير</button>@endif
                @if (!empty($probs[$d->id]))<button type="button" class="btn sm" style="background:#fde8e8;color:#c62828;font-weight:800" data-p="{{ implode("\n", $probs[$d->id]) }}" onclick="alert('⚠ المشكلة:\n\n'+this.dataset.p)">⚠ مشكلة ({{ count($probs[$d->id]) }})</button>@endif
                @if ($isDep)<a class="btn ghost sm" target="_blank" href="{{ route('sg.dispatch.receipt', $d) }}">🧾 إيصال</a>@endif
                <button type="button" class="kd {{ $isDep ? 'deposit' : 'order' }}" title="اضغط للتبديل" onclick="ra({{ $d->id }}, 'kind')">{{ $isDep ? '💵 عربون' : '📦 طلبية' }}</button>
                @endif
                <button type="button" class="btn ghost sm" style="color:#c62828" title="حذف" onclick="ra({{ $d->id }}, 'delete', 'نحذفوه نهائي؟ ما يرجعش{{ $d->orders->count() ? ' (الإيصالات ترجع للرف)' : '' }}')">🗑</button>
            </div></td>
            <td><b>{{ ['cash' => '💵 كاش', 'bank' => '🏦 مصرف', 'mixed' => '💵+🏦', 'paid' => '✅ مدفوعة'][$d->pay_type] ?? '—' }}</b>@if ($d->bank_receipt)<a class="chip u" style="display:inline-block;margin-top:3px" target="_blank" href="{{ asset('storage/' . $d->bank_receipt) }}">📎 الإيصال</a>@endif</td>
            <td class="w"><b class="num">{{ optional($d->customer)->code }}</b> {{ optional($d->customer)->name ?: $d->recipient_name }}<small class="note num" style="display:block">{{ $ph($d->recipient_phone) }}</small></td>
            <td class="w"><b>{{ trim(($d->city ?: '') . (($d->area ?? null) ? ' · ' . $d->area : '')) ?: '' }}</b>@if ($d->address)<small class="note" style="display:block">{{ $d->address }}</small>@endif @if ($d->note)<small class="note" style="display:block">{{ \Illuminate\Support\Str::limit($d->note, 90) }}</small>@endif</td>
            <td class="w num">@if ($d->cart_id)<b>🛒 حجز سلة #{{ $d->cart_id }}</b>@else{{ $d->orders->pluck('code')->implode('، ') ?: '—' }}@endif
                @if ($d->kind !== 'deposit' && !$d->cart_id && $d->orders->count())<small style="display:block;color:#000;font-size:12px">🛍 أكياس: <b>{{ $d->orders->sum(fn ($o) => (int) $o->bags_num) ?: $d->parcels }}</b> · 🗄 الرف: <b>{{ $d->orders->pluck('storage_num')->filter()->unique()->implode('، ') ?: '—' }}</b></small>@endif
                @foreach ($probs[$d->id] ?? [] as $pr)<small style="display:block;color:#c62828;font-weight:700">{{ $pr }}</small>@endforeach</td>
            <td class="n num"><b>{{ $f($d->amount) }}</b></td>
        </tr>
    @empty
        <tr><td colspan="7"><div class="empty">ما فيش شي في الطابور</div></td></tr>
    @endforelse
    </tbody></table></div>
</form>
<form method="post" id="rowAct" style="display:none">@csrf<input type="hidden" name="a" id="raA"></form>

<div class="modal-bg" id="mDl"><div class="modal" style="max-width:460px">
    <h3 id="dlT">✅ تم التسليم</h3>
    <div class="hint mb" id="dlI"></div>
    <form method="post" id="dlF">@csrf<input type="hidden" name="a" id="dlA">
        <div class="grid g2">
            <div class="f"><label class="l" id="dlAL">دفع كم؟ (د.ل)</label><input name="amount" id="dlAm" class="inp numr" inputmode="decimal" required></div>
            <div class="f"><label class="l">نوع الدفع</label><select name="payment_id" id="dlP" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }}</option>@endforeach</select></div>
        </div>
        <div class="f"><label class="l">ملاحظة (اختياري)</label><input name="note" class="inp" placeholder="مثلاً: سلّمها فلان · الباقي دين"></div>
        <div class="note mb" id="dlH"></div>
        <div style="display:flex;gap:8px"><button class="btn green" id="dlB">✅ تم التسليم</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>

<div class="modal-bg" id="mEd"><div class="modal" style="max-width:560px">
    <h3>✏ تعديل الدليفري <span class="num" id="edC"></span></h3>
    <form method="post" id="edF">@csrf<input type="hidden" name="a" value="edit">
        <div class="grid g2">
            <div class="f"><label class="l">الاسم</label><input name="recipient_name" id="edN" class="inp"></div>
            <div class="f"><label class="l">الهاتف</label><input name="recipient_phone" id="edPh" class="inp numr"></div>
            <div class="f"><label class="l">المدينة</label><input name="city" id="edCt" class="inp" list="lyCities"></div>
            <div class="f"><label class="l">المنطقة</label><input name="area" id="edAr" class="inp"></div>
        </div>
        <div class="f"><label class="l">العنوان</label><input name="address" id="edAd" class="inp"></div>
        <div class="grid g2">
            <div class="f"><label class="l" id="edAmL">القيمة (د.ل)</label><input name="amount" id="edAm" class="inp numr" inputmode="decimal"></div>
            <div class="f"><label class="l">نوع الدفع</label><select name="pay_type" id="edPt" class="inp"><option value="cash">💵 كاش</option><option value="bank">🏦 مصرف</option><option value="mixed">💵+🏦</option><option value="paid">✅ مدفوعة</option></select></div>
            <div class="f"><label class="l">نوع الطلب</label><select name="kind" id="edK" class="inp"><option value="order">📦 طلبية</option><option value="deposit">💵 عربون</option></select></div>
        </div>
        <div class="f"><label class="l">ملاحظة</label><input name="note" id="edNt" class="inp"></div>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@include('sg.partials.cities')

{{-- الجاهز في الرف --}}
<form method="post" action="{{ route('sg.dispatch.assign') }}" id="asg" class="card tw">
    @csrf
    <div class="tw-h"><h3>📦 جاهز في الرف <span class="h-note">{{ $ready->count() }} زبون</span></h3>
        <input type="search" class="inp" style="margin-inline-start:auto;width:220px;height:38px" placeholder="🔍 كود / اسم / هاتف" value="{{ $q }}" onkeydown="if(event.key==='Enter'){event.preventDefault();location='?tab={{ $tab }}&q='+encodeURIComponent(this.value)}">
    </div>
    <div class="scroll-x"><table class="t"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.pk').forEach(x=>x.checked=this.checked);cnt()"></th><th>الزبون</th><th>العنوان</th><th>الإيصالات · التخزين</th><th class="n">الباقي مع الشحن</th><th>النوع</th></tr></thead><tbody>
    @forelse ($ready as $cid => $os)
        @php $c = $os->first()->customer; @endphp
        @continue(!$c)
        <tr>
            <td><input type="checkbox" class="pk" name="c[]" value="{{ $cid }}" onchange="cnt()"></td>
            <td class="w"><b class="num">{{ $c->code }}</b> {{ $c->name }}<small class="note num" style="display:block">{{ $ph($c->phone_1) }}</small></td>
            <td class="w">{{ $c->address ?: '—' }}</td>
            <td class="w num">{{ $os->pluck('code')->implode('، ') }}<small class="note" style="display:block">📍 {{ $os->pluck('storage_num')->filter()->unique()->implode('، ') ?: '—' }} · {{ max(1, $os->sum(fn ($o) => $o->bags_num ?: 1)) }} طرد</small></td>
            <td class="n num"><b>{{ $f($os->sum(fn ($o) => max(0, $o->remaining()))) }}</b></td>
            <td><select name="kind[{{ $cid }}]" class="inp" style="height:32px;width:auto"><option value="order">📦 طلبية</option><option value="deposit">💵 عربون</option></select></td>
        </tr>
    @empty
        <tr><td colspan="6"><div class="empty">ما فيش طلبيات جاهزة{{ $q !== '' ? ' بالبحث هذا' : '' }}</div></td></tr>
    @endforelse
    </tbody></table></div>
    <div class="abar">
        <b>المختار: <span id="nsel" class="num">0</span></b>
        <select name="zone" class="inp"><option value="in" @selected($tab === 'in')>🛵 بنغازي</option><option value="out" @selected($tab === 'out')>🚚 خارج بنغازي</option></select>
        <button class="btn green" style="margin-inline-start:auto">➕ أضف لقائمة الدليفري</button>
    </div>
</form>
</div>
@if (session('print_url'))<script>window.open(@json(session('print_url')), '_blank');</script>@endif
<script>
const RA = @json(route('sg.dispatch.act', 0)).replace(/0$/, ''), PDF = @json(route('sg.dispatch.pdf'));
function cq(){document.getElementById('nq').textContent=document.querySelectorAll('.qk:checked').length}
function sel(){return [...document.querySelectorAll('.qk:checked')].map(x=>x.value)}
function bk(a){if(!sel().length){alert('علّم على الزبائن الأول');return}document.getElementById('bkA').value=a;document.getElementById('bk').submit()}
function pdfSel(){const s=sel();if(!s.length){alert('علّم على الزبائن الأول');return}window.open(PDF+'?tab={{ $tab }}&ids='+s.join(','),'_blank')}
function ra(id,a,q){if(q&&!confirm(q))return;const f=document.getElementById('rowAct');f.action=RA+id;document.getElementById('raA').value=a;f.submit()}
function fee(i){const fd=new FormData();fd.set('a','fee');fd.set('fee',i.value||0);fd.set('_token',document.querySelector('meta[name=csrf-token]').content);
  fetch(RA+i.dataset.id,{method:'POST',body:fd,headers:{'Accept':'application/json'},credentials:'same-origin'}).then(r=>{i.style.borderColor=r.ok?'#0f8a5f':'#c62828'}).catch(()=>{i.style.borderColor='#c62828'})}
function postTo(u,data,q){if(q&&!confirm(q))return;const f=document.createElement('form');f.method='post';f.action=u;data._token=document.querySelector('meta[name=csrf-token]').content;for(const k in data){const i=document.createElement('input');i.type='hidden';i.name=k;i.value=data[k];f.appendChild(i)}document.body.appendChild(f);f.submit()}
function collect(id,amt){const v=prompt('كم قبض المندوب (العربون بدون التوصيل)؟ لو نفسها اضغط OK',amt);if(v===null)return;const n=parseFloat(v);if(isNaN(n)||n<0){alert('اكتب رقم صحيح');return}
  const f=document.getElementById('rowAct');f.action=RA+id;document.getElementById('raA').value='collect';let i=f.querySelector('input[name=amount]');if(!i){i=document.createElement('input');i.type='hidden';i.name='amount';f.appendChild(i)}i.value=n;f.submit()}
function ext(i){const fd=new FormData();fd.set('a','ext');fd.set('ext_code',i.value);fd.set('_token',document.querySelector('meta[name=csrf-token]').content);
  fetch(RA+i.dataset.id,{method:'POST',body:fd,headers:{'Accept':'application/json'},credentials:'same-origin'}).then(r=>{i.style.borderColor=r.ok?'#0f8a5f':'#c62828'}).catch(()=>{i.style.borderColor='#c62828'})}
function settle(id,amt){const v=prompt('كم سلمتلك شركة التوصيل؟ (بدون رسالة للعميل)',amt);if(v===null)return;const n=parseFloat(v);if(isNaN(n)||n<0){alert('اكتب رقم صحيح');return}
  const p=document.getElementById('payOut');postTo(RA+id,{a:'settle',amount:n,payment_id:p?p.value:''})}
function cp(b){navigator.clipboard.writeText(b.dataset.copy||'');const t=b.textContent;b.textContent='✓ تنسخ';setTimeout(()=>b.textContent=t,1500)}
function silentAmt(id,amt,dep){const v=prompt(dep?'كم قبض المندوب من العربون؟':'كم دفع العميل؟ (بدون رسالة للعميل — يطلعلك الإيصال)',amt);if(v===null)return;const n=parseFloat(v);if(isNaN(n)||n<0){alert('اكتب رقم صحيح');return}postTo(RA+id,{a:'silent',amount:n})}
function cnt(){document.getElementById('nsel').textContent=document.querySelectorAll('.pk:checked').length}
const CASH = {{ (int) $cashId }};
function openDl(a,d){const dep=a==='confirm';document.getElementById('dlF').action=RA+d.id;document.getElementById('dlA').value=a;
  document.getElementById('dlT').textContent=dep?'✓ تأكيد العربون':'✅ تم التسليم';
  document.getElementById('dlB').textContent=dep?'✓ تأكيد':'✅ تم التسليم';
  document.getElementById('dlAL').textContent=dep?'العربون اللي دفعه (د.ل)':'دفع كم؟ (د.ل)';
  document.getElementById('dlI').innerHTML='<b>'+(d.ccode||'')+'</b> '+(d.name||'')+' · '+(d.what||'')+'<br>'+(dep?'العربون':'الباقي')+': <b class="num">'+d.amount+'</b> د.ل';
  document.getElementById('dlH').textContent=dep?(d.cart?'يتعمل الإيصال، تتحجز السلة في قروب منتظرة، ويطلعلك الإيصال للطباعة':'الدفعة تتسجل على الإيصال (البضاعة تقعد في الرف) ويطلعلك الإيصال'):'الإيصالات تصير مستلمة والمبلغ يدخل الخزينة · لو دفع أقل يقعد الباقي دين · يطلعلك إيصال التسليم';
  document.getElementById('dlAm').value=d.amount;const p=document.getElementById('dlP');
  if(d.pay_type==='bank'){const o=[...p.options].find(x=>+x.value!==CASH);if(o)p.value=o.value}else p.value=CASH;
  document.getElementById('mDl').classList.add('open')}
function openEd(d){document.getElementById('edF').action=RA+d.id;document.getElementById('edC').textContent=d.code;
  const v={edN:d.name,edPh:d.phone,edCt:d.city,edAr:d.area,edAd:d.address,edAm:d.amount,edNt:d.note,edPt:d.pay_type||'cash',edK:d.kind};
  for(const k in v){document.getElementById(k).value=v[k]??''}
  document.getElementById('edAmL').textContent=d.kind==='deposit'?'العربون (د.ل)':'الباقي (د.ل)';
  document.getElementById('mEd').classList.add('open')}
</script>
@endsection
