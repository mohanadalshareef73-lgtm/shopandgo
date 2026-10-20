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
    <div><h1>🛵 طلبيات دليفري</h1><div class="sub">اختار الزبائن الجاهزين ← اعطيهم لمندوب (بحساب ولا بالاسم) ← اطبع الكشف PDF</div></div>
    <div class="acts"><a class="btn ghost" href="{{ route('sg.couriers') }}">المندوبين</a><button class="sg-burger">☰</button></div>
</div>

<div class="dtabs">
    <a href="?tab=in" class="{{ $tab === 'in' ? 'on' : '' }}">🛵 مع المناديب ({{ (int) ($counts['in'] ?? 0) }})</a>
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
            <td>@if (!($r->cart_id && $r->status === 'delivered'))<button type="button" class="btn sm" style="background:#262c4a;color:#fff" onclick="ra({{ $r->id }}, 'restore', 'نرجعوها للطابور زي ما كانت؟{{ $r->status === 'delivered' ? ' (الدفعة اللي تسجلت وقتها ترجع)' : '' }}')">↩ رجّعها</button>@else<span class="note">من صفحة الإيصال</span>@endif</td>
        </tr>
    @endforeach
    </tbody></table>
</details>
@endif

{{-- الطابور: منو مستلمها · علّم ← كشف / ترحيل / تعيين / رجوع --}}
<form method="post" action="{{ route('sg.dispatch.bulk') }}" id="bk" class="card tw mb">
    @csrf <input type="hidden" name="a" id="bkA">
    <div class="tw-h"><h3>{{ $tab === 'out' ? '🚚 طابور خارج بنغازي' : '🛵 طلبيات مع المناديب' }} <span class="h-note">{{ $queue->count() }} زبون · {{ $f($queue->sum('amount')) }} د.ل + توصيل {{ $f($queue->sum('fee')) }}</span></h3>
        @foreach ($batches as $bt)<a class="btn ghost sm" target="_blank" href="{{ route('sg.dispatch.pdf', ['batch' => $bt]) }}">📄 {{ $bt }}</a>@endforeach
    </div>
    <div class="qbar" style="background:#fff">
        @foreach (['wait' => '⏳ مازال ما استلمهاش مندوب', 'taken' => '🛵 استلمها مندوب', 'all' => 'الكل'] as $k => $lb)
            <a class="btn sm {{ $fl === $k ? '' : 'ghost' }}" href="?tab={{ $tab }}&f={{ $k }}">{{ $lb }} <b class="num">({{ $fc[$k] }})</b></a>
        @endforeach
    </div>
    @if ($tab === 'out')
    <div class="qbar" style="background:#fff4e5">
        <input type="search" class="inp" style="width:220px" placeholder="🔍 كود شركة التوصيل / كود العميل" value="{{ $xq }}" onkeydown="if(event.key==='Enter'){event.preventDefault();location='?tab=out&xq='+encodeURIComponent(this.value)}">
        @if ($xq !== '')<a class="btn ghost sm" href="?tab=out">✕ مسح</a>@endif
        <span class="sep"></span>
        <label class="note">التحصيل في</label><select name="payment_id" id="payOut" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }}</option>@endforeach</select>
        <button type="button" class="btn sm" style="background:#e8590c;color:#fff" onclick="if(confirm('نحصّلو المختارين بقيمتهم كاملة؟ (بدون رسالة للعميل)'))bk('settle')">💰 تحصيل المختار</button>
    </div>
    @endif
    @if ($queue->count())
    <div class="qbar">
        <b>المختار: <span id="nq" class="num">0</span></b>
        <button type="button" class="btn sm" onclick="pdfSel()">📄 كشف PDF للمختار</button>
        <button type="button" class="btn green sm" onclick="if(confirm('المختارين مستلمين بدون رسالة؟ الباقي يتسجل مقبوض كاش'))bk('silent')">✅ مستلمة (بدون رسالة)</button>
        <button type="button" class="btn ghost sm" onclick="bk('{{ $tab === 'out' ? 'move_in' : 'move_out' }}')">{{ $tab === 'out' ? '🛵 ترحيل للمندوب' : '🚚 ترحيل خارج بنغازي' }}</button>
        <span class="sep"></span>
        <select name="courier_id" class="inp"><option value="">— مندوب —</option>@foreach ($couriers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
        <input name="courier_name" class="inp" style="width:150px" placeholder="✍ اسم المندوب">
        <button type="button" class="btn sm" style="background:#262c4a;color:#fff" onclick="bk('courier')">🛵 استلمها المندوب</button>
        <button type="button" class="btn ghost sm" style="color:var(--down)" onclick="if(confirm('نرجعو المختارين للمحل والرف؟'))bk('back')">↩ للرف</button>
    </div>
    @endif
    <div class="scroll-x"><table class="t"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.qk').forEach(x=>x.checked=this.checked);cq()"></th><th>إجراء</th><th>مع</th><th>الزبون</th><th>العنوان</th><th>الإيصالات · التخزين</th><th class="n">الباقي</th></tr></thead><tbody>
    @forelse ($queue as $d)
        @php $who = $d->courier_name ?: optional($d->courier)->name; @endphp
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
                <button type="button" class="btn ghost sm" onclick="const t=prompt('📝 الملاحظة اللي تبي تبعتها للعميل على الواتساب:');if(t&&t.trim())postTo(RA+{{ $d->id }},{a:'note',text:t})">📝 ملاحظة</button>
                @if ($tab === 'out')
                <input class="inp" style="width:120px;height:28px;font-size:12.5px" placeholder="كود الشركة" value="{{ $d->ext_code }}" data-id="{{ $d->id }}" onchange="ext(this)">
                <button type="button" class="btn sm" style="background:#e8590c;color:#fff" onclick="settle({{ $d->id }}, {{ (float) $d->amount + 0 }})">💰 تحصيل</button>
                @endif
                @if ($d->cart_id)<button type="button" class="btn sm" style="background:#0f8a5f;color:#fff" onclick="collect({{ $d->id }}, {{ (float) $d->amount + 0 }})">✓ تأكيد</button>
                @else<button type="button" class="btn green sm" onclick="silentAmt({{ $d->id }}, {{ (float) $d->amount + 0 }}, {{ $d->kind === 'deposit' ? 1 : 0 }})">{{ $d->kind === 'deposit' ? '✅ قبض العربون' : '✅ مستلمة' }}</button>@endif
                <button type="button" class="btn ghost sm" onclick="ra({{ $d->id }}, 'done', 'نفتحو صفحة التسليم باش تدير الدفع بنفسك؟')">💵 بالكاشير</button>
                @if (!empty($probs[$d->id]))<button type="button" class="btn sm" style="background:#fde8e8;color:#c62828;font-weight:800" data-p="{{ implode("\n", $probs[$d->id]) }}" onclick="alert('⚠ المشكلة:\n\n'+this.dataset.p)">⚠ مشكلة ({{ count($probs[$d->id]) }})</button>@endif
                @if ($d->kind === 'deposit')<a class="btn ghost sm" target="_blank" href="{{ route('sg.dispatch.receipt', $d) }}">🧾 إيصال</a>@endif
                <button type="button" class="kd {{ $d->kind === 'deposit' ? 'deposit' : 'order' }}" title="اضغط للتبديل" onclick="ra({{ $d->id }}, 'kind')">{{ $d->kind === 'deposit' ? '💵 عربون' : '📦 طلبية' }}</button>
                @endif
                <button type="button" class="btn ghost sm" style="color:#c62828" title="حذف" onclick="ra({{ $d->id }}, 'delete', 'نحذفوه نهائي؟ ما يرجعش{{ $d->orders->count() ? ' (الإيصالات ترجع للرف)' : '' }}')">🗑</button>
            </div></td>
            <td><b>{{ $who ?: '— ما تعيّنش' }}</b>@if (!$d->courier_id && $d->courier_name)<small class="note" style="display:block">بدون حساب</small>@endif</td>
            <td class="w"><b class="num">{{ optional($d->customer)->code }}</b> {{ optional($d->customer)->name ?: $d->recipient_name }}<small class="note num" style="display:block">{{ $ph($d->recipient_phone) }}</small>@if ($d->bank_receipt)<a class="chip u" target="_blank" href="{{ asset('storage/' . $d->bank_receipt) }}">📎 دافع تحويل</a>@endif</td>
            <td class="w">{{ $d->address ?: '—' }}@if ($d->note)<small class="note" style="display:block">{{ \Illuminate\Support\Str::limit($d->note, 90) }}</small>@endif</td>
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
        <select name="zone" class="inp"><option value="in" @selected($tab === 'in')>🛵 مع مندوب</option><option value="out" @selected($tab === 'out')>🚚 خارج بنغازي</option></select>
        <select name="courier_id" class="inp"><option value="">— مندوب بحساب —</option>@foreach ($couriers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
        <span class="note">ولا</span>
        <input name="courier_name" class="inp" style="width:160px" placeholder="اكتب اسم المندوب">
        <label class="note">توصيل للكل <input name="fee" class="inp numr" style="width:70px" value="{{ $defFee + 0 }}" oninput="document.querySelectorAll('.fc').forEach(x=>x.value=this.value)"> د.ل</label>
        <button class="btn green" style="margin-inline-start:auto">✓ تعيين وطباعة الكشف</button>
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
function cnt(){document.getElementById('nsel').textContent=document.querySelectorAll('.pk:checked').length}</script>
@endsection
