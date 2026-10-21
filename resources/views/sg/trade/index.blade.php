@extends('sg.layout')
@section('title', 'التجارة الخارجية')
@php use App\Services\TreasuryService as TS; use App\Models\Trade\Move; $f = fn ($n) => TS::fmt($n); $company = $partners->firstWhere('is_company', true); @endphp
@push('style')
<style>
.tt { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.tt a { padding: 8px 14px; border-radius: 12px; background: #fff; border: 1px solid var(--line); font-weight: 700; }
.tt a.on { background: #262c4a; color: #fff; border-color: #262c4a; }
.tk { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-bottom: 14px; }
.tk .card { padding: 14px; } .tk .l { font-size: 13px; color: var(--ink-3); } .tk .v { font-size: 22px; font-weight: 800; }
.pl { color: #0f8a5f; font-weight: 800; } .mn { color: #c62828; font-weight: 800; }
.fm { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; } .fm .inp { height: 38px; width: auto; }
.shr td { padding: 4px !important; } .shr .inp { height: 34px; }
table.t td.w { white-space: normal; }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>🌍 التجارة الخارجية</h1><div class="sub">خزينة ومالية بروحها · الشركاء · فواتير الشراء · الأرباح على قد اللي دفعه كل واحد</div></div>
    <div class="acts"><button class="btn" data-modal="mInv">+ فاتورة شراء</button><button class="sg-burger">☰</button></div>
</div>

<div class="tt">
    @foreach (['home' => '🏠 الملخص', 'partners' => '👥 الشركاء', 'invoices' => '🧾 الفواتير', 'moves' => '📜 سجل الحركة', 'report' => '📊 الأرباح والخسائر'] as $k => $lb)<a href="?tab={{ $k }}" class="{{ $tab === $k ? 'on' : '' }}">{{ $lb }}</a>@endforeach
</div>

<div class="tk">
    @foreach ($wallets as $w)<div class="card"><div class="l">{{ $w->name }} <form method="post" action="{{ route('sg.trade.wallet.update', $w) }}" style="display:inline" onsubmit="const v=prompt('✏️ الرصيد الصحيح لـ{{ $w->name }}؟',{{ (float) $w->balance + 0 }});if(v===null||v.trim()===''||isNaN(parseFloat(v)))return false;this.balance.value=parseFloat(v);return confirm('نعدلو الرصيد لـ '+v+'؟ الفرق يتسجل في سجل الحركة')">@csrf<input type="hidden" name="balance"><button class="btn ghost sm" style="padding:0 6px;min-height:0;height:22px" title="تعديل الرصيد">✏️</button></form></div><div class="v num">{{ $f($w->balance) }}</div></div>@endforeach
    <div class="card"><div class="l">🏢 رصيد Shop & Go</div><div class="v num">{{ $f(optional($company)->balance) }}</div></div>
    <div class="card"><div class="l">🧾 فواتير مفتوحة</div><div class="v num">{{ $stats['open'] }}</div></div>
    <div class="card"><div class="l">⏳ على العملاء</div><div class="v num">{{ $f($stats['due']) }}</div></div>
    <div class="card"><div class="l">📈 أرباح مقفولة</div><div class="v num {{ $stats['profit'] < 0 ? 'mn' : 'pl' }}">{{ $f($stats['profit']) }}</div></div>
</div>

@if ($tab === 'home')
<div class="grid g2 mb">
    <div class="card" style="padding:14px">
        <h3 style="margin-top:0">➕ إيداع / ➖ سحب لشريك</h3>
        <form method="post" action="{{ route('sg.trade.money') }}" class="fm">@csrf
            <select name="a" class="inp"><option value="deposit">➕ إيداع</option><option value="withdraw">➖ سحب</option></select>
            <select name="partner_id" class="inp" required>@foreach ($partners as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $f($p->balance) }})</option>@endforeach</select>
            <select name="wallet_id" class="inp">@foreach ($wallets as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
            <input name="amount" class="inp numr" style="width:110px" placeholder="المبلغ" required>
            <input name="note" class="inp" style="width:150px" placeholder="ملاحظة">
            <button class="btn">حفظ</button>
        </form>
    </div>
    <div class="card" style="padding:14px">
        <h3 style="margin-top:0">🏢 Shop & Go ↔ خزينة الشركة</h3>
        <form method="post" action="{{ route('sg.trade.money') }}" class="fm" data-confirm="متأكد؟ الفلوس تتحرك بين خزينة الشركة والتجارة">@csrf
            <select name="a" class="inp"><option value="company_in">⬅ زيد رصيد Shop & Go (يطلع من خزينة الشركة)</option><option value="to_company">➡ إدراج ربح لمالية الشركة</option></select>
            <select name="payment_id" class="inp" title="خزينة الشركة">@foreach ($payments as $pm)<option value="{{ $pm->id }}">{{ $pm->name }}</option>@endforeach</select>
            <select name="wallet_id" class="inp" title="خزينة التجارة">@foreach ($wallets as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
            <input name="amount" class="inp numr" style="width:110px" placeholder="المبلغ" required>
            <input name="kind" class="inp" style="width:140px" placeholder="نوع الخصم">
            <button class="btn">تنفيذ</button>
        </form>
    </div>
</div>
@endif

@if ($tab === 'home' || $tab === 'partners')
<div class="card tw mb">
    <div class="tw-h"><h3>👥 الشركاء</h3>
        <form method="post" action="{{ route('sg.trade.partner') }}" class="fm" style="margin-inline-start:auto">@csrf<input name="name" class="inp" placeholder="اسم شريك جديد" required><input name="phone" class="inp" style="width:130px" placeholder="الهاتف"><button class="btn sm">+ شريك</button></form>
    </div>
    <table class="t"><thead><tr><th>الشريك</th><th class="n">الرصيد</th><th class="n">دفع في فواتير</th><th class="n">أرباحه</th><th></th></tr></thead><tbody>
    @foreach ($partners as $p)
        <tr><td><b>{{ $p->name }}</b>{{ $p->is_company ? ' 🏢' : '' }}<small class="note" style="display:block">{{ $p->phone }}</small></td>
            <td class="n num"><b>{{ $f($p->balance) }}</b></td>
            <td class="n num">{{ $f($p->shares()->sum('paid')) }}</td>
            <td class="n num {{ $p->shares()->sum('profit') < 0 ? 'mn' : 'pl' }}">{{ $f($p->shares()->sum('profit')) }}</td>
            <td><a class="btn ghost sm" href="?tab=moves&p={{ $p->id }}">📜 سجله</a> <button type="button" class="btn ghost sm" data-modal="mP{{ $p->id }}">✏️ تعديل</button>
                <div class="modal-bg" id="mP{{ $p->id }}"><div class="modal">
                    <h3>✏️ {{ $p->name }}</h3>
                    <form method="post" action="{{ route('sg.trade.partner.update', $p) }}">@csrf
                        <div class="f"><label class="l">الاسم</label><input name="name" class="inp" value="{{ $p->name }}" required></div>
                        <div class="f"><label class="l">الهاتف</label><input name="phone" class="inp numr" value="{{ $p->phone }}"></div>
                        <div class="f"><label class="l">💰 الرصيد (يتسجل الفرق في سجله)</label><input name="balance" class="inp numr" value="{{ (float) $p->balance + 0 }}"></div>
                        <div class="f"><label class="l">سبب التعديل</label><input name="note" class="inp" placeholder="مثلاً: تصحيح"></div>
                        <div style="display:flex;gap:8px;margin-top:10px"><button class="btn green">💾 حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
                    </form>
                </div></div></td></tr>
    @endforeach
    </tbody></table>
</div>
@endif

@if ($tab === 'home' || $tab === 'invoices')
<div class="card tw mb">
    <div class="tw-h"><h3>🧾 الفواتير</h3></div>
    <div class="scroll-x"><table class="t"><thead><tr><th>الفاتورة</th><th>النوع · الموقع</th><th>العميل</th><th class="n">$</th><th class="n">التكلفة</th><th class="n">البيع</th><th class="n">سدد</th><th>الشركاء</th><th>الحالة</th></tr></thead><tbody>
    @forelse ($invoices as $i)
        <tr>
            <td><a href="{{ route('sg.trade.invoice', $i) }}"><b class="num">{{ $i->code }}</b></a><small class="note num" style="display:block">{{ $i->created_at->format('Y-m-d') }}</small></td>
            <td class="w">{{ $i->kind }}{{ $i->source ? ' · ' . $i->source : '' }}</td>
            <td>{{ optional($i->customer)->name ?: ($i->customer_name ?? '') ?: '—' }}</td>
            <td class="n num">{{ $i->usd_total ? $f($i->usd_total) : '—' }}</td>
            <td class="n num">{{ $f($i->totalCost()) }}</td>
            <td class="n num">{{ $f($i->sale_lyd) }}</td>
            <td class="n num">{{ $f($i->collected) }}</td>
            <td class="w">{{ $i->shares->map(fn ($s) => optional($s->partner)->name . ' ' . $f($s->paid))->implode(' · ') }}</td>
            <td>@if ($i->status === 'closed')<span class="chip u">مقفولة · <span class="{{ $i->profit < 0 ? 'mn' : 'pl' }}">{{ $f($i->profit) }}</span></span>@else<span class="chip w">مفتوحة</span>@endif</td>
        </tr>
    @empty
        <tr><td colspan="9"><div class="empty">ما فيش فواتير — اضغط «+ فاتورة شراء»</div></td></tr>
    @endforelse
    </tbody></table></div>
</div>
@endif

@if ($tab === 'moves')
<div class="card tw mb">
    <div class="tw-h"><h3>📜 سجل الحركة {{ $sel ? '· ' . $sel->name : '' }}</h3>
        <form method="get" class="fm" style="margin-inline-start:auto"><input type="hidden" name="tab" value="moves"><select name="p" class="inp" onchange="this.form.submit()"><option value="">كل الحركات</option>@foreach ($partners as $p)<option value="{{ $p->id }}" @selected($sel && $sel->id === $p->id)>{{ $p->name }}</option>@endforeach</select></form>
    </div>
    <div class="scroll-x"><table class="t"><thead><tr><th>الوقت</th><th>الحركة</th><th>الشريك</th><th>الفاتورة</th><th>الخزينة</th><th class="n">الخزينة ±</th><th class="n">الرصيد ±</th><th class="n">رصيده بعد</th><th>ملاحظة</th></tr></thead><tbody>
    @forelse ($moves as $m)
        <tr>
            <td class="num">{{ $m->created_at->format('m-d g:i a') }}</td>
            <td>{{ Move::TYPES[$m->type] ?? $m->type }}</td>
            <td>{{ optional($m->partner)->name ?: '—' }}</td>
            <td>@if ($m->invoice)<a href="{{ route('sg.trade.invoice', $m->invoice) }}" class="num">{{ $m->invoice->code }}</a>@else—@endif</td>
            <td>{{ optional($m->wallet)->name ?: '—' }}</td>
            <td class="n num {{ $m->wallet_change < 0 ? 'mn' : ($m->wallet_change > 0 ? 'pl' : '') }}">{{ $m->wallet_id ? $f($m->wallet_change) : '' }}</td>
            <td class="n num {{ $m->partner_change < 0 ? 'mn' : ($m->partner_change > 0 ? 'pl' : '') }}">{{ $m->partner_id ? $f($m->partner_change) : '' }}</td>
            <td class="n num">{{ $m->partner_id ? $f($m->partner_after) : '' }}</td>
            <td class="w note">{{ $m->note }}{{ $m->user ? ' · ' . $m->user->name : '' }}</td>
        </tr>
    @empty
        <tr><td colspan="9"><div class="empty">ما فيش حركات</div></td></tr>
    @endforelse
    </tbody></table></div>
</div>
@endif

@if ($tab === 'report')
@php
    // 📈 الربح المتوقع من الفواتير المفتوحة (سعر البيع − التكلفة) حتى لو العميل ما دفعش · على النسبة
    $expBy = []; $invRows = [];
    foreach ($allInv as $iv) {
        $w = \App\Services\TradeService::weights($iv);
        $pr = $iv->status === 'closed' ? (float) $iv->profit : round((float) $iv->sale_lyd - $iv->totalCost(), 2);
        $cells = [];
        foreach ($iv->shares as $sh) {
            $pp = $iv->status === 'closed' ? (float) $sh->profit : round($pr * ($w[$sh->id] ?? 0), 2);
            $cells[$sh->partner_id] = ['pct' => round(($w[$sh->id] ?? 0) * 100, 1), 'amt' => $pp, 'paid' => (float) $sh->paid];
            if ($iv->status !== 'closed') { $expBy[$sh->partner_id] = ($expBy[$sh->partner_id] ?? 0) + $pp; }
        }
        $invRows[] = ['iv' => $iv, 'profit' => $pr, 'cells' => $cells];
    }
@endphp
<div class="card tw mb">
    <div class="tw-h"><h3>📊 كل شريك</h3></div>
    <div class="scroll-x"><table class="t"><thead><tr><th>الشريك</th><th class="n">إيداع</th><th class="n">سحب</th><th class="n">من خزينة الشركة</th><th class="n">لمالية الشركة</th><th class="n">في فواتير مفتوحة</th><th class="n">أرباح</th><th class="n">خسائر</th><th class="n">الصافي</th><th class="n">📈 متوقع (مفتوحة)</th><th class="n">= الكل</th><th class="n">الرصيد</th></tr></thead><tbody>
    @foreach ($partners as $p)
        @php $r = ($report[$p->id] ?? collect())->keyBy('type'); $g = fn ($t) => (float) optional($r->get($t))->s; $open = (float) $p->shares()->whereHas('invoice', fn ($q) => $q->where('status', 'open'))->sum('paid'); @endphp
        <tr><td><b>{{ $p->name }}</b>{{ $p->is_company ? ' 🏢' : '' }}</td>
            <td class="n num">{{ $f($g('deposit')) }}</td><td class="n num">{{ $f(abs($g('withdraw'))) }}</td>
            <td class="n num">{{ $f($g('company_in')) }}</td><td class="n num">{{ $f(abs($g('to_company'))) }}</td>
            <td class="n num">{{ $f($open) }}</td>
            <td class="n num pl">{{ $f($g('profit')) }}</td><td class="n num mn">{{ $f(abs($g('loss'))) }}</td>
            <td class="n num {{ $g('profit') + $g('loss') < 0 ? 'mn' : 'pl' }}"><b>{{ $f($g('profit') + $g('loss')) }}</b></td>
            @php $ex = (float) ($expBy[$p->id] ?? 0); @endphp
            <td class="n num {{ $ex < 0 ? 'mn' : 'pl' }}">{{ $f($ex) }}</td>
            <td class="n num {{ $g('profit') + $g('loss') + $ex < 0 ? 'mn' : 'pl' }}"><b>{{ $f($g('profit') + $g('loss') + $ex) }}</b></td>
            <td class="n num"><b>{{ $f($p->balance) }}</b></td></tr>
    @endforeach
    </tbody></table></div>
    @if ($company)<div class="note" style="padding:10px 14px">🏢 ربح Shop & Go الصافي: <b class="num">{{ $f((float) optional(($report[$company->id] ?? collect())->firstWhere('type', 'profit'))->s + (float) optional(($report[$company->id] ?? collect())->firstWhere('type', 'loss'))->s) }}</b> د.ل · رصيدها في التجارة <b class="num">{{ $f($company->balance) }}</b> — تقدر تدرجه لمالية الشركة من «🏠 الملخص».</div>@endif
</div>
<div class="card tw mb">
    <div class="tw-h"><h3>🧾 كل فاتورة: نسبة كل شريك وربحه <span class="h-note">المفتوحة: متوقع (سعر البيع − التكلفة) حتى لو العميل ما دفعش</span></h3></div>
    <div class="scroll-x"><table class="t"><thead><tr><th>الفاتورة</th><th>العميل</th><th class="n">التكلفة</th><th class="n">البيع</th><th class="n">سدد</th><th class="n">الربح</th>@foreach ($partners as $p)<th class="n">{{ $p->name }}</th>@endforeach</tr></thead><tbody>
    @forelse ($invRows as $row)
        @php $iv = $row['iv']; @endphp
        <tr><td><a href="{{ route('sg.trade.invoice', $iv) }}"><b class="num">{{ $iv->code }}</b></a> @if ($iv->status === 'closed')<span class="chip u">مقفولة</span>@else<span class="chip w">مفتوحة</span>@endif</td>
            <td>{{ optional($iv->customer)->name ?: ($iv->customer_name ?? '') ?: '—' }}</td>
            <td class="n num">{{ $f($iv->totalCost()) }}</td><td class="n num">{{ $f($iv->sale_lyd) }}</td><td class="n num">{{ $f($iv->collected) }}</td>
            <td class="n num {{ $row['profit'] < 0 ? 'mn' : 'pl' }}"><b>{{ $f($row['profit']) }}</b></td>
            @foreach ($partners as $p)
                @php $c = $row['cells'][$p->id] ?? null; @endphp
                <td class="n num">@if ($c)<b class="{{ $c['amt'] < 0 ? 'mn' : 'pl' }}">{{ $f($c['amt']) }}</b><small class="note" style="display:block">{{ $c['pct'] }}% · دفع {{ $f($c['paid']) }}</small>@else—@endif</td>
            @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ 6 + $partners->count() }}"><div class="empty">ما فيش فواتير</div></td></tr>
    @endforelse
    </tbody></table></div>
</div>
<div class="card tw mb">
    <div class="tw-h"><h3>📅 حسب الشهر (فواتير مقفولة)</h3></div>
    <table class="t"><thead><tr><th>الشهر</th><th class="n">فواتير</th><th class="n">التكلفة</th><th class="n">سدد العملاء</th><th class="n">الربح</th></tr></thead><tbody>
    @forelse ($monthly as $m)
        <tr><td class="num">{{ $m->m }}</td><td class="n num">{{ $m->n }}</td><td class="n num">{{ $f($m->c) }}</td><td class="n num">{{ $f($m->s) }}</td><td class="n num {{ $m->p < 0 ? 'mn' : 'pl' }}"><b>{{ $f($m->p) }}</b></td></tr>
    @empty
        <tr><td colspan="5"><div class="empty">ما فيش فواتير مقفولة</div></td></tr>
    @endforelse
    </tbody></table>
</div>
@endif

{{-- ➕ فاتورة شراء --}}
<div class="modal-bg" id="mInv"><div class="modal" style="max-width:820px;width:100%">
    <h3>🧾 فاتورة شراء جديدة</h3>
    <form method="post" action="{{ route('sg.trade.invoice.store') }}" id="invF">@csrf
        <div class="grid g3">
            <div><label class="l">👤 اسم العميل</label><input name="customer_name" class="inp" value="{{ old('customer_name') }}"></div>
            <div><label class="l">🔢 كود العميل (لو عنده حساب)</label><input name="customer_code" class="inp numr" value="{{ old('customer_code') }}">
                <label class="note" style="display:flex;gap:6px;align-items:center;margin-top:6px"><input type="checkbox" name="to_account" value="1" @checked(old('to_account', '1'))> 📦 تنزل الفاتورة في حساب العميل</label></div>
            <div><label class="l">🌐 الموقع</label><input name="source" class="inp" value="{{ old('source') }}" placeholder="Amazon · Shein · …"></div>
            <div><label class="l">💲 الإجمالي بالدولار</label><input name="usd_total" id="iUsd" class="inp numr" value="{{ old('usd_total') }}" oninput="calc()"></div>
            <div><label class="l">🇱🇾 دفعت بالليبي كم</label><input name="cost_lyd" id="iCost" class="inp numr" value="{{ old('cost_lyd') }}" oninput="calc()" placeholder="ولا يتحسب: $ × سعر الصرف"></div>
            <div><label class="l">💱 سعر الصرف (تلقائي)</label><input name="rate" id="iRate" class="inp numr" value="{{ old('rate') }}" placeholder="ينحسب وحده" oninput="rateIn()"></div>
            <div><label class="l">🏷 سعر البيع للعميل</label><div style="display:flex;gap:4px"><input name="sale_lyd" id="iSale" class="inp numr" style="flex:1" value="{{ old('sale_lyd') }}" oninput="calc()"><select name="sale_cur" id="iSaleCur" class="inp" style="width:auto" onchange="calc()"><option value="lyd">د.ل</option><option value="usd" @selected(old('sale_cur') === 'usd')>$</option></select></div></div>
            <div><label class="l">📈 الربح (تلقائي)</label><input id="iProfit" class="inp numr" readonly style="font-weight:800"></div>
            <div><label class="l">📦 الكمية</label><input name="qty" class="inp numr" value="{{ old('qty', 1) }}"></div>
            <div><label class="l">💵 العميل دفع كم توا</label><div style="display:flex;gap:4px"><input name="cust_paid" class="inp numr" style="flex:1" value="{{ old('cust_paid') }}"><select name="cust_cur" class="inp" style="width:auto"><option value="lyd">د.ل</option><option value="usd" @selected(old('cust_cur') === 'usd')>$</option></select></div></div>
            <div><label class="l">➕ مصاريف إضافية د.ل</label><input name="extra_lyd" id="iExtra" class="inp numr" value="{{ old('extra_lyd', 0) }}" oninput="calc()"></div>
            <div><label class="l">تندفع من</label><select name="wallet_id" class="inp">@foreach ($wallets as $w)<option value="{{ $w->id }}">{{ $w->name }} ({{ $f($w->balance) }})</option>@endforeach</select></div>
            <input type="hidden" name="kind" value="{{ old('kind') }}">
        </div>
        <h4 style="margin:12px 0 6px">🛍 المنتجات <span class="note">(اختياري — تنزل في طلبية العميل)</span> <button type="button" class="btn ghost sm" onclick="addItem('itB')">+ منتج</button></h4>
        <table class="t" style="display:none"><thead><tr><th>اسم المنتج</th><th>العدد</th><th>السعر د.ل</th><th></th></tr></thead><tbody id="itB"></tbody></table>
        <h4 style="margin:12px 0 6px">👥 الشركاء <span class="note">— اللي ما دفعش خليه فاضي وحطله نسبة بس · لو رصيده ما يكفيش يقعد عليه دين</span> <span class="note" id="sumN"></span></h4>
        <table class="t shr"><thead><tr><th>الشريك</th><th>دفع د.ل</th><th>نسبة الربح %</th><th>من وين</th><th>نوع الخصم (للشركة)</th></tr></thead><tbody>
        @foreach ($partners->where('active', true)->values() as $k => $p)
            <tr><td><input type="hidden" name="s[{{ $k }}][partner_id]" value="{{ $p->id }}"><b>{{ $p->name }}</b>{{ $p->is_company ? ' 🏢' : '' }}<small class="note" style="display:block">رصيده {{ $f($p->balance) }}</small></td>
                <td><input name="s[{{ $k }}][paid]" class="inp numr sp" style="width:110px" oninput="sum()"></td>
                <td><input name="s[{{ $k }}][pct]" class="inp numr spc" style="width:80px" placeholder="تلقائي" oninput="sum()"></td>
                <td><select name="s[{{ $k }}][source]" class="inp"><option value="balance">من رصيده</option><option value="cash">دفع توا للتجارة</option>@if ($p->is_company)@foreach ($payments as $pm)<option value="main:{{ $pm->id }}">خزينة الشركة · {{ $pm->name }}</option>@endforeach @endif</select></td>
                <td>@if ($p->is_company)<input name="s[{{ $k }}][kind]" class="inp" style="width:140px" placeholder="نوع الخصم">@endif</td></tr>
        @endforeach
        </tbody></table>
        <div class="grid g2" style="margin-top:8px"><div><label class="l">ملاحظات</label><input name="note" class="inp" value="{{ old('note') }}"></div></div>
        <div style="display:flex;gap:8px;margin-top:12px"><button class="btn green">حفظ الفاتورة</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
<script>
const V=id=>parseFloat(document.getElementById(id).value)||0;
let costAuto=false;
// 💱 الدولار × سعر الصرف = الليبي (لو الليبي فاضي) · الليبي ÷ الدولار = سعر الصرف (لو كتبت الليبي)
function rateIn(){const u=V('iUsd'),r=V('iRate'),ce=document.getElementById('iCost');if(u&&r&&(!ce.value||costAuto)){ce.value=Math.round(u*r*100)/100;costAuto=true}calc(true)}
function calc(fromRate){const u=V('iUsd'),ce=document.getElementById('iCost'),re=document.getElementById('iRate');
  if(document.activeElement===ce)costAuto=false;
  if(!fromRate){if(u&&V('iCost')&&!costAuto)re.value=Math.round(V('iCost')/u*10000)/10000;else if(u&&V('iRate')&&(!ce.value||costAuto)){ce.value=Math.round(u*V('iRate')*100)/100;costAuto=true}}
  const c=V('iCost'),r=V('iRate'),usd=document.getElementById('iSaleCur').value==='usd',sale=usd?V('iSale')*r:V('iSale');
  const p=V('iSale')?sale-c-V('iExtra'):0;const e=document.getElementById('iProfit');e.value=V('iSale')?Math.round(p*100)/100+' د.ل':'';e.style.color=p<0?'#c62828':'#0f8a5f';sum()}
function sum(){let s=0,pc=0;document.querySelectorAll('.sp').forEach(i=>s+=parseFloat(i.value)||0);document.querySelectorAll('.spc').forEach(i=>pc+=parseFloat(i.value)||0);
  const n=document.getElementById('sumN');n.textContent='· دفعوا '+s.toLocaleString('en-US')+(pc?' · النسب '+pc+'%'+(Math.abs(pc-100)<0.01?' ✓':' (لازم 100%)'):' · الربح على قد اللي دفعه كل واحد');n.style.color=pc&&Math.abs(pc-100)>0.01?'#c62828':'#0f8a5f'}
@if ($errors->any() || old('cost_lyd'))document.addEventListener('DOMContentLoaded',()=>{const b=document.querySelector('[data-modal=mInv]');b&&b.click()});@endif
</script>
<script>let itN=50;
// 🛍 المنتجات: ما تطلعش لين تضغط «+ منتج» · كمية كل منتج تنجمع في خانة الكمية
function addItem(id){const b=document.getElementById(id);b.closest('table').style.display='';const r=document.createElement('tr');r.innerHTML='<td><input name="items['+itN+'][name]" class="inp" placeholder="اسم المنتج"></td><td><input name="items['+itN+'][qty]" class="inp numr iq" style="width:70px" value="1" oninput="sumQty(this)"></td><td><input name="items['+itN+'][price]" class="inp numr" style="width:110px"></td><td><button type="button" class="btn ghost sm" style="color:#c62828" onclick="const f=this.closest(\'form\');this.closest(\'tr\').remove();sumQty(f)">✕</button></td>';itN++;b.appendChild(r);r.querySelector('input').focus();sumQty(b)}
function sumQty(el){const f=el.closest?el.closest('form'):el;if(!f)return;const q=f.querySelectorAll('.iq');if(!q.length)return;let t=0;q.forEach(i=>t+=parseInt(i.value)||0);const m=f.querySelector('input[name=qty]');if(m)m.value=Math.max(1,t)}
</script>
@endsection
