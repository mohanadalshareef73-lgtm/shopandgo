@extends('sg.layout')
@section('title', 'طلبيات دليفري')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
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

{{-- الطابور: منو مستلمها · علّم ← كشف / ترحيل / تعيين / رجوع --}}
<form method="post" action="{{ route('sg.dispatch.bulk') }}" id="bk" class="card tw mb">
    @csrf <input type="hidden" name="a" id="bkA">
    <div class="tw-h"><h3>{{ $tab === 'out' ? '🚚 طابور خارج بنغازي' : '🛵 طلبيات مع المناديب' }} <span class="h-note">{{ $queue->count() }} زبون · {{ $f($queue->sum('amount')) }} د.ل + توصيل {{ $f($queue->sum('fee')) }}</span></h3>
        @foreach ($batches as $bt)<a class="btn ghost sm" target="_blank" href="{{ route('sg.dispatch.pdf', ['batch' => $bt]) }}">📄 {{ $bt }}</a>@endforeach
    </div>
    @if ($queue->count())
    <div class="qbar">
        <b>المختار: <span id="nq" class="num">0</span></b>
        <button type="button" class="btn sm" onclick="pdfSel()">📄 كشف PDF للمختار</button>
        <button type="button" class="btn green sm" onclick="if(confirm('المختارين مستلمين بدون رسالة؟ الباقي يتسجل مقبوض كاش'))bk('silent')">✅ مستلمة (بدون رسالة)</button>
        <button type="button" class="btn ghost sm" onclick="bk('{{ $tab === 'out' ? 'move_in' : 'move_out' }}')">{{ $tab === 'out' ? '🛵 ترحيل للمندوب' : '🚚 ترحيل خارج بنغازي' }}</button>
        <span class="sep"></span>
        <select name="courier_id" class="inp"><option value="">— مندوب —</option>@foreach ($couriers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
        <input name="courier_name" class="inp" style="width:120px" placeholder="ولا اكتب اسم">
        <button type="button" class="btn ghost sm" onclick="bk('courier')">⇄ عطيهم له</button>
        <button type="button" class="btn ghost sm" style="color:var(--down)" onclick="if(confirm('نرجعو المختارين للمحل والرف؟'))bk('back')">↩ للرف</button>
    </div>
    @endif
    <div class="scroll-x"><table class="t"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.qk').forEach(x=>x.checked=this.checked);cq()"></th><th>إجراء</th><th>مع</th><th>الزبون</th><th>العنوان</th><th>الإيصالات · التخزين</th><th class="n">الباقي</th><th class="n">توصيل</th></tr></thead><tbody>
    @forelse ($queue as $d)
        @php $who = $d->courier_name ?: optional($d->courier)->name; @endphp
        <tr>
            <td><input type="checkbox" class="qk" name="ids[]" value="{{ $d->id }}" onchange="cq()"></td>
            <td><div class="qa">
                <button type="button" class="btn green sm" onclick="ra({{ $d->id }}, 'silent', '{{ $d->kind === 'deposit' ? 'العربون (' . $f($d->amount) . ' د.ل) يتسجل مقبوض كاش على الإيصال، والبضاعة تقعد في الرف؟' : 'تسليم بدون رسالة؟ الباقي (' . $f($d->amount) . ' د.ل) يتسجل مقبوض كاش' }}')">{{ $d->kind === 'deposit' ? '✅ قبض العربون' : '✅ مستلمة' }}</button>
                <button type="button" class="btn ghost sm" onclick="ra({{ $d->id }}, 'done', 'نفتحو صفحة التسليم باش تدير الدفع بنفسك؟')">💵 بالكاشير</button>
                <button type="button" class="kd {{ $d->kind === 'deposit' ? 'deposit' : 'order' }}" title="اضغط للتبديل" onclick="ra({{ $d->id }}, 'kind')">{{ $d->kind === 'deposit' ? '💵 عربون' : '📦 طلبية' }}</button>
            </div></td>
            <td><b>{{ $who ?: '— ما تعيّنش' }}</b>@if (!$d->courier_id && $d->courier_name)<small class="note" style="display:block">بدون حساب</small>@endif @if ($d->assigned_at)<small class="note num" style="display:block">تعيّن {{ optional($d->assigned_at)->format('m-d g:i a') }}</small>@endif</td>
            <td class="w"><b class="num">{{ optional($d->customer)->code }}</b> {{ optional($d->customer)->name ?: $d->recipient_name }}<small class="note num" style="display:block">{{ $d->recipient_phone }}</small>@if ($d->bank_receipt)<a class="chip u" target="_blank" href="{{ asset('storage/' . $d->bank_receipt) }}">📎 دافع تحويل</a>@endif</td>
            <td class="w">{{ $d->address ?: '—' }}@if ($d->note)<small class="note" style="display:block">{{ \Illuminate\Support\Str::limit($d->note, 90) }}</small>@endif</td>
            <td class="w num">@if ($d->cart_id)<b>🛒 حجز سلة #{{ $d->cart_id }}</b>@else{{ $d->orders->pluck('code')->implode('، ') ?: '—' }}@endif<small class="num" style="display:block;color:#000;font-size:11.5px">🕒 {{ optional($d->created_at)->format('Y-m-d g:i a') }}</small><small class="note" style="display:block">📍 {{ $d->orders->pluck('storage_num')->filter()->unique()->implode('، ') ?: '—' }} · {{ $d->parcels }} طرد</small></td>
            <td class="n num"><b>{{ $f($d->amount) }}</b></td>
            <td class="n"><input class="inp numr fe" style="width:64px;height:32px" value="{{ $d->fee + 0 }}" data-id="{{ $d->id }}" onchange="fee(this)" title="سعر التوصيل"></td>
        </tr>
    @empty
        <tr><td colspan="8"><div class="empty">ما فيش شي في الطابور</div></td></tr>
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
    <div class="scroll-x"><table class="t"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.pk').forEach(x=>x.checked=this.checked);cnt()"></th><th>الزبون</th><th>العنوان</th><th>الإيصالات · التخزين</th><th class="n">الباقي مع الشحن</th><th class="n">توصيل</th><th>النوع</th></tr></thead><tbody>
    @forelse ($ready as $cid => $os)
        @php $c = $os->first()->customer; @endphp
        @continue(!$c)
        <tr>
            <td><input type="checkbox" class="pk" name="c[]" value="{{ $cid }}" onchange="cnt()"></td>
            <td class="w"><b class="num">{{ $c->code }}</b> {{ $c->name }}<small class="note num" style="display:block">{{ $c->phone_1 }}</small></td>
            <td class="w">{{ $c->address ?: '—' }}</td>
            <td class="w num">{{ $os->pluck('code')->implode('، ') }}<small class="note" style="display:block">📍 {{ $os->pluck('storage_num')->filter()->unique()->implode('، ') ?: '—' }} · {{ max(1, $os->sum(fn ($o) => $o->bags_num ?: 1)) }} طرد</small></td>
            <td class="n num"><b>{{ $f($os->sum(fn ($o) => max(0, $o->remaining()))) }}</b></td>
            <td class="n"><input name="fee_c[{{ $cid }}]" class="inp numr fc" style="width:64px;height:32px" value="{{ $defFee + 0 }}"></td>
            <td><select name="kind[{{ $cid }}]" class="inp" style="height:32px;width:auto"><option value="order">📦 طلبية</option><option value="deposit">💵 عربون</option></select></td>
        </tr>
    @empty
        <tr><td colspan="7"><div class="empty">ما فيش طلبيات جاهزة{{ $q !== '' ? ' بالبحث هذا' : '' }}</div></td></tr>
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
function cnt(){document.getElementById('nsel').textContent=document.querySelectorAll('.pk:checked').length}</script>
@endsection
