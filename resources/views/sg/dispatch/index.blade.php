@extends('sg.layout')
@section('title', 'طلبيات دليفري')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
@push('style')
<style>
.dtabs { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.dtabs a { padding: 9px 16px; border-radius: 12px; background: #fff; border: 1px solid var(--line); font-weight: 700; }
.dtabs a.on { background: var(--brand); color: #fff; border-color: var(--brand); }
table.t td.w { white-space: normal; min-width: 160px; }
.abar { position: sticky; bottom: 0; z-index: 20; background: #fff; border-top: 2px solid var(--brand); padding: 12px 14px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.abar .inp { height: 38px; width: auto; }
.kd { font-size: 12px; padding: 2px 8px; border-radius: 8px; border: 0; cursor: pointer; font: inherit; }
.kd.order { background: #e7f0ff; color: #1c45a3; } .kd.deposit { background: #fff1e6; color: #b5480f; }
.qa { display: flex; gap: 4px; flex-wrap: wrap; } .qa form { display: inline-flex; gap: 4px; }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>🛵 طلبيات دليفري</h1><div class="sub">اختار الزبائن الجاهزين ← اعطيهم لمندوب (بحساب ولا بالاسم) ← اطبع الكشف PDF</div></div>
    <div class="acts"><a class="btn ghost" href="{{ route('sg.couriers') }}">المندوبين</a><button class="sg-burger">☰</button></div>
</div>

<div class="dtabs">
    <a href="?tab=in" class="{{ $tab === 'in' ? 'on' : '' }}">🛵 مع المناديب ({{ (int) ($counts['in'] ?? 0) }})</a>
    <a href="?tab=out" class="{{ $tab === 'out' ? 'on' : '' }}">🚚 خارج بنغازي ({{ (int) ($counts['out'] ?? 0) }})</a>
</div>

{{-- الطابور: منو مستلمها، ونسحبوها ولا نرجعوها --}}
<div class="card tw mb">
    <div class="tw-h"><h3>{{ $tab === 'out' ? '🚚 طابور خارج بنغازي' : '🛵 طلعات مع المناديب' }} <span class="h-note">{{ $queue->count() }} زبون · يقبضوا {{ $f($queue->sum('amount')) }} د.ل + توصيل {{ $f($queue->sum('fee')) }}</span></h3>
        @if ($queue->count())<a class="btn sm" style="margin-inline-start:auto" target="_blank" href="{{ route('sg.dispatch.pdf', ['tab' => $tab]) }}">📄 كشف الطابور PDF</a>@endif
        @foreach ($batches as $b)<a class="btn ghost sm" target="_blank" href="{{ route('sg.dispatch.pdf', ['batch' => $b]) }}">📄 {{ $b }}</a>@endforeach
    </div>
    <div class="scroll-x"><table class="t"><thead><tr><th>مع</th><th>الكود</th><th>الزبون</th><th>الهاتف</th><th>العنوان</th><th>الإيصالات</th><th>التخزين</th><th class="n">طرود</th><th class="n">الباقي</th><th class="n">توصيل</th><th>النوع</th><th>إجراء</th></tr></thead><tbody>
    @forelse ($queue as $d)
        @php $who = $d->courier_name ?: optional($d->courier)->name; @endphp
        <tr>
            <td><b>{{ $who ?: '— ما تعيّنش' }}</b>@if (!$d->courier_id && $d->courier_name)<small class="note" style="display:block">بدون حساب</small>@endif<small class="note num" style="display:block">{{ optional($d->assigned_at)->format('m-d g:i a') }}</small></td>
            <td class="num"><b>{{ optional($d->customer)->code }}</b></td>
            <td>{{ optional($d->customer)->name }}</td>
            <td class="num">{{ $d->recipient_phone }}</td>
            <td class="w">{{ $d->address }}</td>
            <td class="num w">{{ $d->orders->pluck('code')->implode('، ') }}</td>
            <td class="num">{{ $d->orders->pluck('storage_num')->filter()->unique()->implode('، ') ?: '—' }}</td>
            <td class="n num">{{ $d->parcels }}</td>
            <td class="n num"><b>{{ $f($d->amount) }}</b></td>
            <td class="n num">{{ $f($d->fee) }}</td>
            <td><form method="post" action="{{ route('sg.dispatch.act', $d) }}">@csrf<input type="hidden" name="a" value="kind"><button class="kd {{ $d->kind === 'deposit' ? 'deposit' : 'order' }}" title="اضغط للتبديل">{{ $d->kind === 'deposit' ? '💵 عربون' : '📦 طلبية' }}</button></form></td>
            <td><div class="qa">
                <form method="post" action="{{ route('sg.dispatch.act', $d) }}" data-confirm="{{ optional($d->customer)->name }} وصلته؟ نكملو الدفع والتسليم">@csrf<input type="hidden" name="a" value="done"><button class="btn green sm">✓ وصلت</button></form>
                <form method="post" action="{{ route('sg.dispatch.act', $d) }}">@csrf<input type="hidden" name="a" value="courier">
                    <select name="courier_id" class="inp" style="height:32px;width:110px"><option value="">سحب لـ…</option>@foreach ($couriers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select><input name="courier_name" class="inp" style="height:32px;width:90px" placeholder="ولا اسم"><button class="btn ghost sm">⇄</button></form>
                <form method="post" action="{{ route('sg.dispatch.act', $d) }}">@csrf<input type="hidden" name="a" value="move"><button class="btn ghost sm">{{ $d->zone === 'out' ? '🛵 للمندوب' : '🚚 لخارج بنغازي' }}</button></form>
                <form method="post" action="{{ route('sg.dispatch.act', $d) }}" data-confirm="نرجعوها للمحل والرف؟">@csrf<input type="hidden" name="a" value="back"><button class="btn ghost sm" style="color:var(--down)">↩ للرف</button></form>
            </div></td>
        </tr>
    @empty
        <tr><td colspan="12"><div class="empty">ما فيش شي في الطابور</div></td></tr>
    @endforelse
    </tbody></table></div>
</div>

{{-- الجاهز في الرف --}}
<form method="post" action="{{ route('sg.dispatch.assign') }}" id="asg" class="card tw">
    @csrf
    <div class="tw-h"><h3>📦 جاهز في الرف <span class="h-note">{{ $ready->count() }} زبون</span></h3>
        <input type="search" class="inp" style="margin-inline-start:auto;width:220px;height:38px" placeholder="🔍 كود / اسم / هاتف" value="{{ $q }}" onkeydown="if(event.key==='Enter'){event.preventDefault();location='?tab={{ $tab }}&q='+encodeURIComponent(this.value)}">
    </div>
    <div class="scroll-x"><table class="t"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.pk').forEach(x=>x.checked=this.checked);cnt()"></th><th>الكود</th><th>الزبون</th><th>الهاتف</th><th>العنوان</th><th>الإيصالات</th><th>التخزين</th><th class="n">طرود</th><th class="n">الباقي مع الشحن</th><th>النوع</th></tr></thead><tbody>
    @forelse ($ready as $cid => $os)
        @php $c = $os->first()->customer; @endphp
        @continue(!$c)
        <tr>
            <td><input type="checkbox" class="pk" name="c[]" value="{{ $cid }}" onchange="cnt()"></td>
            <td class="num"><b>{{ $c->code }}</b></td>
            <td>{{ $c->name }}</td>
            <td class="num">{{ $c->phone_1 }}</td>
            <td class="w">{{ $c->address ?: '—' }}</td>
            <td class="num w">{{ $os->pluck('code')->implode('، ') }}</td>
            <td class="num">{{ $os->pluck('storage_num')->filter()->unique()->implode('، ') ?: '—' }}</td>
            <td class="n num">{{ max(1, $os->sum(fn ($o) => $o->bags_num ?: 1)) }}</td>
            <td class="n num"><b>{{ $f($os->sum(fn ($o) => max(0, $o->remaining()))) }}</b></td>
            <td><select name="kind[{{ $cid }}]" class="inp" style="height:32px;width:auto"><option value="order">📦 طلبية</option><option value="deposit">💵 عربون</option></select></td>
        </tr>
    @empty
        <tr><td colspan="10"><div class="empty">ما فيش طلبيات جاهزة{{ $q !== '' ? ' بالبحث هذا' : '' }}</div></td></tr>
    @endforelse
    </tbody></table></div>
    <div class="abar">
        <b>المختار: <span id="nsel" class="num">0</span></b>
        <select name="zone" class="inp"><option value="in" @selected($tab === 'in')>🛵 مع مندوب</option><option value="out" @selected($tab === 'out')>🚚 خارج بنغازي</option></select>
        <select name="courier_id" class="inp"><option value="">— مندوب بحساب —</option>@foreach ($couriers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
        <span class="note">ولا</span>
        <input name="courier_name" class="inp" style="width:160px" placeholder="اكتب اسم المندوب">
        <label class="note">توصيل <input name="fee" class="inp numr" style="width:70px" value="{{ $defFee + 0 }}"> د.ل</label>
        <button class="btn green" style="margin-inline-start:auto">✓ تعيين وطباعة الكشف</button>
    </div>
</form>
@if (session('print_url'))<script>window.open(@json(session('print_url')), '_blank');</script>@endif
<script>function cnt(){document.getElementById('nsel').textContent=document.querySelectorAll('.pk:checked').length}</script>
@endsection
