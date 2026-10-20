@extends('sg.layout')
@section('title', 'إقفال اليومية')
@php
    use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n);
    $types = ['order' => 'دفعة إيصال', 'missing_refund' => 'استرجاع نواقص', 'purchase' => 'مصروف', 'paymentExpenses' => 'مصروفات', 'user' => 'موظف / مرتب',
        'dept' => 'دين', 'shippingCharge' => 'شحن', 'trip' => 'رحلة', 'customer' => 'رصيد عميل', 'transfer' => 'تحويل بين الخزائن'];
    $open = $shift && !$shift->closed_at;
    $exp = (float) $sum['expected'];
@endphp
@push('style')
<style>
.dc-k { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-bottom: 14px; }
.dc-k .card { padding: 14px; } .dc-k .l { font-size: 13px; color: var(--ink-3); } .dc-k .v { font-size: 24px; font-weight: 800; }
.dc-k .in .v { color: #0f8a5f; } .dc-k .out .v { color: #c62828; }
.dc-k .due { background: #262c4a; color: #fff; } .dc-k .due .l { color: #c9cde0; }
table.t td.w { white-space: normal; }
.pl { color: #0f8a5f; font-weight: 800; } .mn { color: #c62828; font-weight: 800; }
@media print { .sg-side, .noprint, .top .acts { display: none !important; } .card { box-shadow: none !important; border: 1px solid #ccc; } }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>📒 إقفال اليومية · {{ $user->name }}</h1><div class="sub">من {{ \Carbon\Carbon::parse($since)->format('Y-m-d g:i a') }} لين {{ \Carbon\Carbon::parse($until)->format('Y-m-d g:i a') }}{{ $open ? ' · الوردية مفتوحة' : ($shift ? ' · وردية مقفولة' : '') }}</div></div>
    <div class="acts noprint">
        @if ($isBoss)<form method="get" style="display:inline-flex;gap:6px"><select name="user" class="inp" style="height:38px;width:auto" onchange="this.form.submit()">@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($s->id === $user->id)>{{ $s->name }}</option>@endforeach</select></form>@endif
        <button class="btn ghost" onclick="window.print()">🖨 طباعة</button><button class="sg-burger">☰</button>
    </div>
</div>

<div class="dc-k">
    <div class="card in"><div class="l">💵 قبض كاش</div><div class="v num">{{ $f($sum['cash_in']) }}</div></div>
    <div class="card out"><div class="l">↩ طلّع من الكاش</div><div class="v num">{{ $f($sum['cash_out']) }}</div></div>
    <div class="card"><div class="l">🏦 قبض مصرف (للعلم)</div><div class="v num">{{ $f($bankIn) }}</div></div>
    <div class="card"><div class="l">📦 تسليمات</div><div class="v num">{{ $delivered->count() }}</div></div>
    <div class="card due"><div class="l">= لازم يسلّم كاش</div><div class="v num">{{ $f($exp) }} <small>د.ل</small></div></div>
</div>
@if ($exp < 0)<div class="flash e mb">⚠ الرقم بالسالب: طلّع من الكاش ({{ $f($sum['cash_out']) }}) أكثر من اللي قبضه ({{ $f($sum['cash_in']) }}). شوف الحركات الحمراء تحت.</div>@endif

@if ($open)
<form method="post" action="{{ route('sg.finance.close') }}" class="card mb noprint" data-confirm="نقفلو يومية {{ $user->name }}؟" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;padding:14px">
    @csrf <input type="hidden" name="user_id" value="{{ $user->id }}">
    <div><label class="l">💵 كم سلّم فعلياً؟</label><input name="counted" class="inp numr" style="width:160px;font-size:20px;font-weight:800" value="{{ round(max(0, $exp), 2) }}" required></div>
    <div style="flex:1;min-width:200px"><label class="l">ملاحظة (اختياري)</label><input name="note" class="inp" maxlength="300"></div>
    <button class="btn green" style="font-size:16px;padding:10px 22px">🔒 إقفال اليومية</button>
</form>
@elseif (!$shift)
<div class="note mb">ما فيش وردية مفتوحة — تنفتح أوتوماتيك مع أول قبض.</div>
@else
<div class="card mb" style="padding:12px 14px">✅ تقفلت {{ $shift->closed_at->format('Y-m-d g:i a') }} · المفروض <b class="num">{{ $f($shift->expected_cash) }}</b> · سلّم <b class="num">{{ $f($shift->counted_cash) }}</b> · الفرق <b class="num {{ abs($shift->diff) < 0.01 ? '' : ($shift->diff < 0 ? 'mn' : 'pl') }}">{{ $f($shift->diff) }}</b>{{ $shift->note ? ' · ' . $shift->note : '' }}</div>
@endif

<div class="card tw mb">
    <div class="tw-h"><h3>💰 سجل الدفعات <span class="h-note">{{ $moves->count() }} حركة</span></h3></div>
    <div class="scroll-x"><table class="t"><thead><tr><th>الوقت</th><th>البيان</th><th>العميل · الإيصال</th><th>الطريقة</th><th class="n">دخل</th><th class="n">طلع</th></tr></thead><tbody>
    @forelse ($moves as $m)
        @php $o = $m->type === 'order' ? ($orders[$m->procedure_id] ?? null) : null; $isCash = (int) $m->payment_id === (int) $cashId; @endphp
        <tr>
            <td class="num">{{ $m->created_at->format('g:i a') }}</td>
            <td class="w">{{ $types[$m->type] ?? $m->type }}@if ($m->note)<small class="note" style="display:block">{{ \Illuminate\Support\Str::limit($m->note, 80) }}</small>@endif</td>
            <td class="w">@if ($o)<a href="{{ route('sg.orders.show', $o) }}"><b class="num">{{ $o->code }}</b></a> {{ optional($o->customer)->name }}@else—@endif</td>
            <td>{{ $pm[$m->payment_id] ?? '—' }}{{ ($m->currency ?: 'dinar') !== 'dinar' ? ' (' . $m->currency . ')' : '' }}</td>
            <td class="n num">@if ($m->status === 'pay')<span class="{{ $isCash ? 'pl' : '' }}">{{ $f($m->price) }}</span>@endif</td>
            <td class="n num">@if ($m->status === 'pull')<span class="{{ $isCash ? 'mn' : '' }}">{{ $f($m->price) }}</span>@endif</td>
        </tr>
    @empty
        <tr><td colspan="6"><div class="empty">ما فيش حركات</div></td></tr>
    @endforelse
    </tbody></table></div>
</div>

<div class="card tw mb">
    <div class="tw-h"><h3>📦 التسليمات <span class="h-note">{{ $delivered->count() }} إيصال</span></h3></div>
    <div class="scroll-x"><table class="t"><thead><tr><th>الوقت</th><th>الإيصال</th><th>العميل</th><th>طريقة التسليم</th><th class="n">المدفوع</th><th class="n">الباقي</th></tr></thead><tbody>
    @forelse ($delivered as $o)
        <tr>
            <td class="num">{{ \Carbon\Carbon::parse($o->delivered_time)->format('g:i a') }}</td>
            <td><a href="{{ route('sg.orders.show', $o) }}"><b class="num">{{ $o->code }}</b></a></td>
            <td class="w"><b class="num">{{ optional($o->customer)->code }}</b> {{ optional($o->customer)->name }}</td>
            <td>{{ $o->delivery_type ?: 'من المحل' }}{{ $o->delivery_name ? ' · ' . $o->delivery_name : '' }}</td>
            <td class="n num">{{ $f($o->paid_price) }}</td>
            <td class="n num {{ $o->remaining() > 0.009 ? 'mn' : '' }}">{{ $f(max(0, $o->remaining())) }}</td>
        </tr>
    @empty
        <tr><td colspan="6"><div class="empty">ما فيش تسليمات</div></td></tr>
    @endforelse
    </tbody></table></div>
</div>

@if ($past->count())
<div class="card tw noprint">
    <div class="tw-h"><h3>🗂 آخر الإقفالات</h3></div>
    <table class="t"><thead><tr><th>التاريخ</th><th class="n">المفروض</th><th class="n">سلّم</th><th class="n">الفرق</th><th></th></tr></thead><tbody>
    @foreach ($past as $p)
        <tr><td class="num">{{ $p->closed_at->format('Y-m-d g:i a') }}</td><td class="n num">{{ $f($p->expected_cash) }}</td><td class="n num">{{ $f($p->counted_cash) }}</td>
            <td class="n num {{ abs($p->diff) < 0.01 ? '' : ($p->diff < 0 ? 'mn' : 'pl') }}">{{ $f($p->diff) }}</td>
            <td><a class="btn ghost sm" href="?user={{ $user->id }}&shift={{ $p->id }}">📒 فتح</a></td></tr>
    @endforeach
    </tbody></table>
</div>
@endif
@endsection
