@php $f = fn ($n) => \App\Services\TreasuryService::fmt($n); $due = $orders->sum(fn ($o) => max(0, $o->remaining())); @endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>كشف حساب · {{ $c->name }}</title>
<link rel="stylesheet" href="{{ asset('assets/sg/sg.css') }}?v=6">
<style>
body { background: #fff; padding: 24px; max-width: 900px; margin: 0 auto; }
.hd { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--brand); padding-bottom: 14px; margin-bottom: 16px; }
.hd img { width: 140px; }
h1 { font-size: 22px; margin: 0; }
.sum { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 14px 0; }
.sum div { background: var(--line-soft); border-radius: 12px; padding: 12px; } .sum small { color: var(--ink-3); display: block; } .sum b { font-family: InterNum; font-size: 20px; }
@media print { .noprint { display: none; } body { padding: 0; } }
</style>
</head>
<body>
@include('partials.back', ['home' => route('customer.dashboard'), 'float' => true])
<div class="hd"><div><h1>كشف حساب</h1><div class="note">{{ $c->name }} · كود {{ $c->code }} · {{ $c->phone_1 }}</div><div class="note">بتاريخ {{ now()->format('Y-m-d') }}</div></div><img src="{{ asset('assets/sg/logo.png') }}" alt=""></div>
<div class="sum"><div><small>إجمالي الطلبات</small><b>{{ $f($orders->sum(fn ($o) => $o->grandTotal())) }}</b> د.ل</div><div><small>المدفوع</small><b>{{ $f($orders->sum('paid_price')) }}</b> د.ل</div><div><small>المستحق</small><b style="color:var(--down)">{{ $f($due) }}</b> د.ل</div></div>
<table class="t"><thead><tr><th>التاريخ</th><th>الإيصال</th><th>البيان</th><th class="n">الإجمالي</th><th class="n">المدفوع</th><th class="n">المتبقي</th></tr></thead><tbody>
@foreach ($orders as $o)
    <tr><td class="num">{{ $o->created_at->format('Y-m-d') }}</td><td class="num">{{ $o->code }}</td><td>{{ $o->kind === 'shipping' ? 'شحن ' . (\App\Services\AppSettings::origins()[$o->ship_origin]['name'] ?? '') . ($o->kilo ? ' · ' . $f($o->kilo) . ' كغ' : '') : (optional($o->platform)->name . ' · ' . $o->qty . ' قطعة') }}{{ $o->delivered ? ' · مُسلّمة' : '' }}</td><td class="n num">{{ $f($o->grandTotal()) }}</td><td class="n num">{{ $f($o->paid_price) }}</td><td class="n num" style="{{ $o->remaining() > 0.009 ? 'color:var(--down);font-weight:700' : '' }}">{{ $f(max(0, $o->remaining())) }}</td></tr>
@endforeach
</tbody></table>
<h3 style="margin-top:22px">الدفعات</h3>
<table class="t"><thead><tr><th>التاريخ</th><th>الإيصال</th><th>طريقة الدفع</th><th class="n">المبلغ</th></tr></thead><tbody>
@forelse ($pays as $p)<tr><td class="num">{{ $p->created_at->format('Y-m-d g:i a') }}</td><td class="num">{{ optional($p->order)->code }}</td><td>{{ optional($p->paymentMethod)->name }}</td><td class="n num">{{ $f($p->paid_price) }}</td></tr>@empty<tr><td colspan="4" class="note">لا توجد دفعات</td></tr>@endforelse
</tbody></table>
<div class="noprint" style="margin-top:20px;display:flex;gap:8px"><button class="btn" onclick="window.print()">🖨 طباعة / حفظ PDF</button><a class="btn ghost" href="{{ route('customer.dashboard', ['tab' => 'account']) }}">رجوع</a></div>
</body>
</html>
