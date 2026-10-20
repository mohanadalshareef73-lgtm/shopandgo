@extends('sg.layout')
@section('title', 'فاتورة ' . $inv->code)
@php use App\Services\TreasuryService as TS; use App\Models\Trade\Move; $f = fn ($n) => TS::fmt($n); $base = $inv->paidByPartners(); $due = max(0, (float) $inv->sale_lyd - (float) $inv->collected); $est = (float) ($inv->status === 'closed' ? $inv->profit : ((float) $inv->sale_lyd - $inv->totalCost())); @endphp
@push('style')
<style>
.tk { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 14px; }
.tk .card { padding: 12px; } .tk .l { font-size: 12.5px; color: var(--ink-3); } .tk .v { font-size: 20px; font-weight: 800; }
.pl { color: #0f8a5f; font-weight: 800; } .mn { color: #c62828; font-weight: 800; }
.fm { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; } .fm .inp { height: 38px; width: auto; }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>🧾 {{ $inv->code }} @if ($inv->status === 'closed')<span class="chip u">مقفولة</span>@else<span class="chip w">مفتوحة</span>@endif</h1>
        <div class="sub">{{ $inv->kind }}{{ $inv->source ? ' · ' . $inv->source : '' }} · {{ $inv->created_at->format('Y-m-d') }}{{ $inv->customer ? ' · العميل ' . $inv->customer->name . ' (' . $inv->customer->code . ')' : '' }}</div></div>
    <div class="acts"><a class="btn ghost" href="{{ route('sg.trade', ['tab' => 'invoices']) }}">↩ الفواتير</a><button class="sg-burger">☰</button></div>
</div>
<div class="tk">
    <div class="card"><div class="l">💲 بالدولار</div><div class="v num">{{ $inv->usd_total ? $f($inv->usd_total) : '—' }}</div><div class="note">سعر الصرف {{ $inv->rate ?: '—' }}</div></div>
    <div class="card"><div class="l">🧾 تكلفة الفاتورة</div><div class="v num">{{ $f($inv->cost_lyd) }}</div><div class="note">+ إضافية {{ $f($inv->extra_lyd) }}</div></div>
    <div class="card"><div class="l">= التكلفة كاملة</div><div class="v num">{{ $f($inv->totalCost()) }}</div></div>
    <div class="card"><div class="l">🏷 سعر البيع</div><div class="v num">{{ $f($inv->sale_lyd) }}</div></div>
    <div class="card"><div class="l">💵 سدد العميل</div><div class="v num pl">{{ $f($inv->collected) }}</div><div class="note">الباقي {{ $f($due) }}</div></div>
    <div class="card"><div class="l">{{ $inv->status === 'closed' ? '📈 الربح' : '📈 الربح المتوقع' }}</div><div class="v num {{ $est < 0 ? 'mn' : 'pl' }}">{{ $f($est) }}</div></div>
</div>

<div class="card mb" style="padding:12px 14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <b>👤 العميل:</b>
    @if ($inv->customer)<span>{{ $inv->customer->name }} · كود <b class="num">{{ $inv->customer->code }}</b></span>@else<span class="note">ما فيش</span>@endif
    @if ($inv->order)<a class="chip u" href="{{ route('sg.orders.show', $inv->order) }}">📦 طلبيته في حسابه: {{ $inv->order->code }} · باقي {{ $f(max(0, $inv->order->remaining())) }}</a>
    @elseif ($inv->customer && $inv->status !== 'closed')<form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}">@csrf<input type="hidden" name="a" value="order"><button class="btn sm">📦 اعمل طلبية في حسابه</button></form>@endif
    @if (!$inv->order && $inv->status !== 'closed')<form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}" class="fm" style="margin-inline-start:auto">@csrf<input type="hidden" name="a" value="customer"><input name="customer_code" class="inp numr" style="width:120px" placeholder="كود العميل" required><button class="btn ghost sm">ربط</button></form>@endif
</div>
@if ($inv->order)<div class="note mb">💡 العميل يقدر يسدد عادي من «التسليم» ولا الإيصال — الفلوس تدخل خزينة التجارة أوتوماتيك وتتحسب هني.</div>@endif

@if ($inv->status !== 'closed')
<div class="card mb" style="padding:14px">
    <form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}" class="fm">@csrf<input type="hidden" name="a" value="pay">
        <b>💵 سداد من العميل</b>
        <input name="amount" class="inp numr" style="width:120px" placeholder="المبلغ" required>
        <select name="wallet_id" class="inp">@foreach ($wallets as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
        <input name="note" class="inp" style="width:160px" placeholder="ملاحظة"><button class="btn green">تسجيل</button>
    </form>
    <form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}" class="fm" style="margin-top:10px">@csrf<input type="hidden" name="a" value="sale">
        <b>🏷 سعر البيع</b><input name="amount" class="inp numr" style="width:120px" value="{{ (float) $inv->sale_lyd + 0 }}"><button class="btn ghost sm">تعديل</button>
    </form>
    <form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}" style="margin-top:12px" data-confirm="نقفلو الفاتورة؟ الربح ({{ $f((float) $inv->collected - $inv->totalCost()) }} د.ل = اللي سدده العميل − التكلفة) يتقسم على الشركاء على قد اللي دفعه كل واحد">@csrf<input type="hidden" name="a" value="close"><button class="btn" style="background:#262c4a;color:#fff">🔒 إقفال الفاتورة وتقسيم الربح</button></form>
</div>
@endif

<div class="card tw mb">
    <div class="tw-h"><h3>👥 الشركاء في الفاتورة</h3></div>
    <table class="t"><thead><tr><th>الشريك</th><th>من وين</th><th class="n">دفع</th><th class="n">النسبة</th><th class="n">{{ $inv->status === 'closed' ? 'نصيبه من الربح' : 'نصيبه المتوقع' }}</th><th class="n">يرجعله كامل</th></tr></thead><tbody>
    @foreach ($inv->shares as $s)
        @php $pct = $base > 0 ? (float) $s->paid / $base : 0; $pr = $inv->status === 'closed' ? (float) $s->profit : round($est * $pct, 2); @endphp
        <tr><td><b>{{ optional($s->partner)->name }}</b></td><td class="note">{{ $s->source }}</td><td class="n num">{{ $f($s->paid) }}</td><td class="n num">{{ round($pct * 100, 1) }}%</td>
            <td class="n num {{ $pr < 0 ? 'mn' : 'pl' }}">{{ $f($pr) }}</td><td class="n num"><b>{{ $f((float) $s->paid + $pr) }}</b></td></tr>
    @endforeach
    </tbody></table>
</div>

<div class="card tw">
    <div class="tw-h"><h3>📜 حركات الفاتورة</h3></div>
    <table class="t"><thead><tr><th>الوقت</th><th>الحركة</th><th>الشريك</th><th>الخزينة</th><th class="n">الخزينة ±</th><th class="n">الرصيد ±</th><th>ملاحظة</th></tr></thead><tbody>
    @foreach ($moves as $m)
        <tr><td class="num">{{ $m->created_at->format('m-d g:i a') }}</td><td>{{ Move::TYPES[$m->type] ?? $m->type }}</td><td>{{ optional($m->partner)->name ?: '—' }}</td><td>{{ optional($m->wallet)->name ?: '—' }}</td>
            <td class="n num {{ $m->wallet_change < 0 ? 'mn' : ($m->wallet_change > 0 ? 'pl' : '') }}">{{ $m->wallet_id ? $f($m->wallet_change) : '' }}</td>
            <td class="n num {{ $m->partner_change < 0 ? 'mn' : ($m->partner_change > 0 ? 'pl' : '') }}">{{ $m->partner_id ? $f($m->partner_change) : '' }}</td>
            <td class="note">{{ $m->note }}</td></tr>
    @endforeach
    </tbody></table>
</div>
@endsection
